<?php include 'includes/header.php'; ?>
<?php
include "./includes/db.php";
echo "DB 연결 성공";
echo "홈페이지 시작 성공";
?>
<style>
.bg-home {
  background-image: url('/img/bg.png');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}
</style>
<body class="bg-home">
<?php include 'includes/footer.php'; ?>