<?php
    $con=file_get_contents("altared_es.mp4"); //kecak.mp4 work to play with <video> </video> tag
    $en=base64_encode($con);
    $binary_data='data:'.$mime.';base64,'. $en ;
?>



<video width="320" height="240" controls="controls">
    <source src="data:video/mp4;<?php echo $binary_data ?>"></video>

</video>
