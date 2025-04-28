<?php

while (1) {
    echo shell_exec('php bin/console process-messages ' . $argv[1]);
}