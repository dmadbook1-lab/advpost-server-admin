$(document).ready(function(){
    //var service = document.getElementById('service');
    $("#service").on('click',function(){
        console.log('hii');
        alert('hii');
        $.ajax({
            url:'index.php/admin/get_service',
            method:'post',
            dataType:'json',
            success:function(data){
    console.log(data);
    
            }
        })

    })
   
})