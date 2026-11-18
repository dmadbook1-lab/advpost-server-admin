#!/usr/bin/env python3
"""
Sync the LOCAL database schema to the PRODUCTION database (Docker on the VPS).

It compares SHOW CREATE TABLE on both sides and generates the SQL that makes
production match local:
  - new tables                    -> CREATE TABLE
  - new columns                   -> ALTER TABLE ... ADD COLUMN
  - changed columns               -> ALTER TABLE ... MODIFY COLUMN
  - new / changed indexes and FKs -> ADD / DROP+ADD
  - things that exist only in production (tables, columns, indexes) are
    REPORTED and never dropped, unless you pass --allow-drop (columns/indexes only).
Data (rows) is never touched. Only schema.

Usage (run from your Mac, inside advpost-server-admin/):
  bin/db_sync.py                 # dry run: print the plan, change nothing
  bin/db_sync.py --out plan.sql  # also save the plan to a file
  bin/db_sync.py --apply         # backup production, confirm, then apply
  bin/db_sync.py --apply --yes   # skip the typed confirmation (still backs up)

Configuration: environment variables or bin/db_sync.conf (KEY=VALUE lines).
See bin/db_sync.conf.example. Local DB settings come from .env (DB_LOCAL_*).
"""
import argparse
import datetime
import gzip
import os
import re
import shlex
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))


# ----------------------------------------------------------------- config ---
def read_kv(path):
    out = {}
    if not os.path.isfile(path):
        return out
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            v = v.strip()
            if len(v) >= 2 and v[0] == v[-1] and v[0] in "\"'":
                v = v[1:-1]
            out[k.strip()] = v
    return out


def load_config():
    env = read_kv(os.path.join(ROOT, ".env"))
    conf = read_kv(os.path.join(ROOT, "bin", "db_sync.conf"))
    get = lambda k, d="": os.environ.get(k) or conf.get(k) or d
    cfg = {
        # local database (from .env)
        "local_host": os.environ.get("DB_LOCAL_HOST") or env.get("DB_LOCAL_HOST", "127.0.0.1"),
        "local_user": os.environ.get("DB_LOCAL_USERNAME") or env.get("DB_LOCAL_USERNAME", "root"),
        "local_pass": os.environ.get("DB_LOCAL_PASSWORD") or env.get("DB_LOCAL_PASSWORD", ""),
        "local_db": os.environ.get("DB_LOCAL_DATABASE") or env.get("DB_LOCAL_DATABASE", ""),
        # production (docker on the VPS)
        "ssh": get("SYNC_SSH_TARGET"),                      # e.g. root@1.2.3.4
        "ssh_opts": get("SYNC_SSH_OPTS"),                   # e.g. -i ~/.ssh/key -p 22
        "remote_dir": get("SYNC_REMOTE_COMPOSE_DIR"),       # folder with docker-compose.yml
        "compose": get("SYNC_COMPOSE_CMD", "docker compose"),
        "service": get("SYNC_DB_SERVICE", "db"),
        "remote_user": get("SYNC_DB_USER", "advpost"),
        "remote_pass": get("SYNC_DB_PASSWORD", "advpost"),
        "remote_db": get("SYNC_DB_NAME", "adbookuser_adbookuser"),
        "ignore": [t for t in get("SYNC_IGNORE_TABLES", "schema_migrations").split(",") if t],
    }
    missing = [k for k in ("local_db", "ssh", "remote_dir") if not cfg[k]]
    if missing:
        sys.exit("Missing config: %s  (see bin/db_sync.conf.example)" % ", ".join(missing))
    return cfg


# ------------------------------------------------------------ run helpers ---
def run_local_mysql(cfg, sql):
    cmd = ["mysql", "-h", cfg["local_host"], "-u", cfg["local_user"], "-N", "-B", "-r", cfg["local_db"]]
    env = dict(os.environ, MYSQL_PWD=cfg["local_pass"])
    p = subprocess.run(cmd, input=sql, capture_output=True, text=True, env=env)
    if p.returncode:
        sys.exit("Local mysql failed:\n" + p.stderr)
    return p.stdout


def remote_cmd(cfg, tool_args):
    """Build the ssh command that runs a mysql tool inside the db container."""
    inner = "cd %s && MYSQL_PWD=%s %s exec -T -e MYSQL_PWD %s %s" % (
        shlex.quote(cfg["remote_dir"]), shlex.quote(cfg["remote_pass"]),
        cfg["compose"], shlex.quote(cfg["service"]), tool_args)
    return ["ssh"] + shlex.split(cfg["ssh_opts"]) + [cfg["ssh"], inner]


def run_remote_mysql(cfg, sql):
    args = "mysql -u%s -N -B -r %s" % (shlex.quote(cfg["remote_user"]), shlex.quote(cfg["remote_db"]))
    p = subprocess.run(remote_cmd(cfg, args), input=sql, capture_output=True, text=True)
    if p.returncode:
        sys.exit("Remote mysql failed:\n" + p.stderr)
    return p.stdout


# ------------------------------------------------------------ schema read ---
def fetch_creates(runner, cfg, db_name):
    names = runner(
        "SELECT table_name FROM information_schema.tables "
        "WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name;"
    ).split()
    names = [n for n in names if n not in cfg["ignore"]]
    if not names:
        return {}
    out = runner("".join("SHOW CREATE TABLE `%s`;\n" % n for n in names))
    creates = {}
    # each result row is "<table>\t<CREATE TABLE ...>"
    parts = re.split(r"(?m)^(?=%s\tCREATE TABLE )" % "(?:%s)" % "|".join(re.escape(n) for n in names), out)
    for part in parts:
        m = re.match(r"([^\t\n]+)\t(CREATE TABLE .*)", part, re.S)
        if m:
            creates[m.group(1)] = m.group(2).strip()
    return creates


def parse_table(create_sql):
    cols, idx, fks, order = {}, {}, {}, []
    lines = create_sql.split("\n")
    for raw in lines[1:]:
        line = raw.strip()
        if line.startswith(")"):
            break
        line = line.rstrip(",")
        if line.startswith("`"):
            name = line[1:line.index("`", 1)]
            cols[name] = line
            order.append(name)
        elif line.startswith("PRIMARY KEY"):
            idx["PRIMARY"] = line
        elif line.startswith("CONSTRAINT"):
            fks[line.split("`")[1]] = line
        else:
            m = re.match(r"(?:UNIQUE |FULLTEXT |SPATIAL )?KEY `([^`]+)`", line)
            if m:
                idx[m.group(1)] = line
    return {"cols": cols, "idx": idx, "fks": fks, "order": order}


def norm(defn):
    """Normalise a definition so cosmetic version differences don't show as changes."""
    d = re.sub(r"\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)", r"\1", defn)
    d = re.sub(r"\s+COLLATE\s+\w+", "", d)
    d = re.sub(r"\s+CHARACTER SET\s+\w+", "", d)
    d = re.sub(r"\bcurrent_timestamp\(\)", "CURRENT_TIMESTAMP", d, flags=re.I)
    d = re.sub(r"\s+DEFAULT\s+NULL\b", "", d)
    d = re.sub(r"\s+", " ", d)
    return d.strip().lower()


def strip_autoinc(create_sql):
    return re.sub(r"\sAUTO_INCREMENT=\d+", "", create_sql)


# ------------------------------------------------------------------- diff ---
def build_plan(local, prod, allow_drop):
    stmts, notes = [], []

    for t in sorted(local):
        if t not in prod:
            stmts.append("-- new table\n%s;" % strip_autoinc(local[t]))
    for t in sorted(prod):
        if t not in local:
            notes.append("Table `%s` exists only in production (not dropped)." % t)

    for t in sorted(local):
        if t not in prod:
            continue
        L, P = parse_table(local[t]), parse_table(prod[t])
        alters = []

        for i, c in enumerate(L["order"]):
            ldef = L["cols"][c]
            if c not in P["cols"]:
                pos = "FIRST" if i == 0 else "AFTER `%s`" % L["order"][i - 1]
                alters.append("ADD COLUMN %s %s" % (ldef, pos))
            elif norm(ldef) != norm(P["cols"][c]):
                alters.append("MODIFY COLUMN %s  /* was: %s */" % (ldef, P["cols"][c].replace("*/", "")))
        for c in P["order"]:
            if c not in L["cols"]:
                if allow_drop:
                    alters.append("DROP COLUMN `%s`" % c)
                else:
                    notes.append("Column `%s`.`%s` exists only in production (not dropped)." % (t, c))

        for name, ldef in L["idx"].items():
            if name not in P["idx"]:
                alters.append("ADD %s" % ldef)
            elif norm(ldef) != norm(P["idx"][name]):
                drop = "DROP PRIMARY KEY" if name == "PRIMARY" else "DROP INDEX `%s`" % name
                alters += [drop, "ADD %s" % ldef]
        for name in P["idx"]:
            if name not in L["idx"]:
                if allow_drop:
                    alters.append("DROP PRIMARY KEY" if name == "PRIMARY" else "DROP INDEX `%s`" % name)
                else:
                    notes.append("Index `%s`.`%s` exists only in production (not dropped)." % (t, name))

        for name, ldef in L["fks"].items():
            if name not in P["fks"]:
                alters.append("ADD %s" % ldef)
            elif norm(ldef) != norm(P["fks"][name]):
                alters += ["DROP FOREIGN KEY `%s`" % name, "ADD %s" % ldef]
        for name in P["fks"]:
            if name not in L["fks"]:
                notes.append("Foreign key `%s`.`%s` exists only in production (not dropped)." % (t, name))

        if alters:
            stmts.append("ALTER TABLE `%s`\n  %s;" % (t, ",\n  ".join(alters)))

    return stmts, notes


# ------------------------------------------------------------------- main ---
def main():
    ap = argparse.ArgumentParser(description="Sync local DB schema to production (Docker on VPS).")
    ap.add_argument("--apply", action="store_true", help="backup production and apply the plan")
    ap.add_argument("--yes", action="store_true", help="skip typed confirmation")
    ap.add_argument("--allow-drop", action="store_true", help="also drop columns/indexes missing locally")
    ap.add_argument("--out", help="write the plan to this .sql file")
    args = ap.parse_args()

    cfg = load_config()
    print("Local : %s@%s/%s" % (cfg["local_user"], cfg["local_host"], cfg["local_db"]))
    print("Prod  : %s -> %s/%s (docker service '%s')" % (cfg["ssh"], cfg["remote_dir"], cfg["remote_db"], cfg["service"]))

    local = fetch_creates(lambda s: run_local_mysql(cfg, s), cfg, cfg["local_db"])
    prod = fetch_creates(lambda s: run_remote_mysql(cfg, s), cfg, cfg["remote_db"])
    print("Tables: %d local, %d production\n" % (len(local), len(prod)))

    stmts, notes = build_plan(local, prod, args.allow_drop)

    for n in notes:
        print("NOTE:", n)
    if notes:
        print()
    if not stmts:
        print("Production schema already matches local. Nothing to do.")
        return

    plan = "SET FOREIGN_KEY_CHECKS=0;\n\n" + "\n\n".join(stmts) + "\n\nSET FOREIGN_KEY_CHECKS=1;\n"
    print("=== Plan (%d statement(s)) ===\n%s" % (len(stmts), plan))
    if args.out:
        with open(args.out, "w", encoding="utf-8") as fh:
            fh.write(plan)
        print("Plan saved to", args.out)

    if not args.apply:
        print("Dry run only. Re-run with --apply to back up and apply.")
        return

    if not args.yes:
        ans = input("Type the production DB name (%s) to apply: " % cfg["remote_db"])
        if ans.strip() != cfg["remote_db"]:
            sys.exit("Aborted.")

    # backup production first
    bdir = os.path.join(ROOT, "storage", "db_backups")
    os.makedirs(bdir, exist_ok=True)
    bfile = os.path.join(bdir, "%s_%s.sql.gz" % (cfg["remote_db"], datetime.datetime.now().strftime("%Y%m%d_%H%M%S")))
    dump_args = "mysqldump -u%s --single-transaction --routines --triggers %s" % (
        shlex.quote(cfg["remote_user"]), shlex.quote(cfg["remote_db"]))
    print("Backing up production to", bfile)
    p = subprocess.run(remote_cmd(cfg, dump_args), capture_output=True)
    if p.returncode or not p.stdout:
        sys.exit("Backup failed, nothing applied:\n" + p.stderr.decode(errors="replace"))
    with gzip.open(bfile, "wb") as fh:
        fh.write(p.stdout)

    print("Applying ...")
    run_remote_mysql(cfg, plan)  # exits on the first error
    print("Done. Backup kept at", bfile)
    print("Restore: gunzip -c %s | ssh ... docker compose exec -T db mysql -u<user> <db>" % bfile)


if __name__ == "__main__":
    main()
