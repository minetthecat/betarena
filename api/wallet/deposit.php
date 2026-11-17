<?php
require_once dirname(__DIR__) . '/_internal/core.php';
run_endpoint('wallet', 'deposit', array('POST'));
