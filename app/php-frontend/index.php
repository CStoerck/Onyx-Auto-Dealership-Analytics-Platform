<?php require 'db.php'; require 'header.php'; ?>

<h1>Welcome to Onyx Auto!</h1>
<p>Find your next dream car or manage our inventory.</p>

<ul>
    <li><a href="search.php"><strong>Browse Public Inventory</strong></a></li>
    <?php if(!$isLoggedIn): ?>
        <li><a href="login.php"><strong>Employee Login</strong></a></li>
    <?php endif; ?>
</ul>

</div></body></html>