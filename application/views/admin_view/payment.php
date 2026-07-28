<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Boost Post Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Razorpay -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

  body {
    margin: 0;
    background: #f5f7fb;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;   /* center ke bajay top se */
    justify-content: center;
    padding-top: 60px;         /* 👈 control yahin se */
}

.payment-card {
    background: #fff;
    width: 100%;
    max-width: 380px;
    padding: 22px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);

    margin-top: -40px;   /* 👈 yahan se upar jayega */
}

        /* HEADER (LOGO + TITLE LEFT) */
 .payment-header {
    display: flex;
    flex-direction: column;   /* vertical */
    align-items: flex-start;  /* left side */
    margin-bottom: 12px;
}

.logo img {
    width: 100px;     /* 👈 width increase */
    height: auto;     /* ratio safe */
    margin-bottom: 8px;
}

.payment-header h2 {
    margin: 0;
    font-size: 18px;
    color: #222;
    text-align: center;   /* 👈 centre */
    width: 100%;          /* 👈 full width so centre properly */
}

        .divider {
            height: 1px;
            background: #eee;
            margin: 16px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
            color: #555;
        }

        .row strong {
            color: #111;
        }

        .amount {
            font-size: 26px;
            font-weight: 600;
            color: #0a58ca;
            text-align: center;
            margin: 16px 0;
        }

        .pay-btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            background: linear-gradient(135deg, #0a58ca, #0d6efd);
            color: #fff;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .pay-btn:active {
            transform: scale(0.98);
        }

        .secure {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 14px;
        }

        /* MOBILE */
        @media (max-width: 360px) {
            .payment-card {
                border-radius: 0;
                height: 100vh;
                max-width: 100%;
            }

            .logo img {
                width: 80px;
            }

            .payment-header h2 {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

<div class="payment-card">

    <!-- HEADER -->
<div class="payment-header">
    <div class="logo">
        <img src="<?= base_url('assetsNew/logo/advpost_logo.jpeg') ?>" alt="Company Logo">
    </div>
    <h2 >Boost Your Post 🚀</h2>
</div>

    <div class="divider"></div>

    <div class="row">
        <span>Post ID</span>
        <strong>#<?= $post_id ?></strong>
    </div>

    <div class="row">
        <span>Service</span>
        <strong>Post Boost</strong>
    </div>

    <div class="divider"></div>

    <div class="amount">
        ₹<?= number_format((float)$amount, 2) ?>
    </div>

    <button class="pay-btn" id="payBtn">
        Pay Now
    </button>

    <div class="secure">
        🔒 100% Secure Payment via Razorpay
    </div>
</div>

<script>
document.getElementById("payBtn").onclick = async function () {

    const res = await fetch("<?= base_url('welcome/create_razorpay_order') ?>", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            post_id: <?= (int)$post_id ?>
        })
    });

    const data = await res.json();

    if (data.error) {
        alert(data.error);
        return;
    }

    const options = {
        key: "rzp_test_SHtjbZaBfQbQDw",
        amount: data.amount,
        currency: "INR",
        name: "Vihaanshika",
        description: "Post Boost Payment",
        order_id: data.order_id,
        handler: function (response) {
            window.location.href =
                "<?= base_url('welcome/payment_success') ?>?"
                + "payment_id=" + response.razorpay_payment_id
                + "&post_id=<?= (int)$post_id ?>";
        },
        theme: {
            color: "#0d6efd"
        }
    };

    new Razorpay(options).open();
};
</script>

</body>
</html>