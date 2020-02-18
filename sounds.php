<?php
system("gpio mode 7 out");
system("gpio write 7 1");
shell_exec("mpg123 -g 10000 sounds/".$argv[1].".mp3");
system("gpio write 7 0");
?>