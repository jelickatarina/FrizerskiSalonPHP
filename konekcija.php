<?php
    $conn=new mysqli("localhost","root", "", "kjfs");
    if(!$conn)
    {
        die("Neuspešna konekcija na bazu podataka." . $sql->error);
    }
?>