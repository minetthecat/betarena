<?php
session_start();
include __DIR__ . '/services/router.php';
?>
<!DOCTYPE html>
<html lang="cs" data-bs-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Bet Aréna</title>
	<!-- Google Fonts Library -->
	<!-- Local Sora stylesheet is not included; system font fallback is used. -->
	<!-- Bootstrap Library -->
	<link href="/libraries/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link href="/libraries/bootstrap/bootstrap-icons.min.css" rel="stylesheet">
	<!-- React Library -->
	<script src="/libraries/react/vendor/react.js"></script>
	<script src="/libraries/react/vendor/react-dom.js"></script>
	<script src="/libraries/react/support.js"></script>
	<!-- Main CSS -->
	<link rel="stylesheet" href="/assets/css/main.css">
	<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>

	<!-- Header -->
	<?php readfile(__DIR__ . '/components/header.html'); ?>

	<!-- Content -->
	<main id="content" class="py-4">
		<?php $render($file); ?>
	</main>

	<!-- Footer -->
	<?php readfile(__DIR__ . '/components/footer.html'); ?>

	<!-- Bootstrap  -->
	<script type="module" src="/libraries/bootstrap/bootstrap.bundle.min.js"></script>
	<!-- Main Services JS -->
	<script src="/services/header.js"></script>
	<script src="/services/router.js"></script>
	<script src="/services/api.js"></script>
	<script src="/services/wallet.js"></script>
	<script src="/services/transaction.js"></script>
	<!-- Main JS -->
	<script src="/assets/js/main.js"></script>
	<script src="/modules/auth.js"></script>
</body>
</html>