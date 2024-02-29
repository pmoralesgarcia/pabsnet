<?php include "./templates/header.php"; ?>
Books
<ul>
<!-- 	<?php
	     if($_SERVER['X-Vouch-User'])
echo '<li><a href="create"><strong>Create Guestbook Entry</strong></a> - Come say hello!</li>';
else
echo 'Please sign in using your domain to post on guestbook.';
			?>

<br>
			<?php
				     if($_SERVER['X-Vouch-User'] == 'https://lifeofpablo.com/')
					     echo '
<h3>Admin</h3>
<ul>
	<li><a href="update.php"><strong>Update</strong></a> - edit a user</li>
	<li><a href="delete.php"><strong>Delete</strong></a> - delete a manual guestbook entry</li>
</ul>
';
else
echo '<a href="panel">Panel</a>';
			?> -->

	<li><a href="update"><strong>Read Guestbook Entries</strong></a> -See other visitors posts!</li>
</ul>
<ul>
	<li><a href="books/create.php"><strong>Create</strong></a> - add a book</li>
	<li><a href="books/read.php"><strong>Find</strong></a> - find a book</li>
	<li><a href="books/update.php"><strong>View</strong></a> - View/edit a book</li>
</ul>
CD's
<ul>
	<li><a href="cds/create.php"><strong>Create</strong></a> - add a CD</li>
	<li><a href="cds/read.php"><strong>Find</strong></a> - find a cd</li>
	<li><a href="cds/update.php"><strong>View</strong></a> - View/edit a cd</li>
</ul>

Vinyls
<ul>
	<li><a href="vinyls/create.php"><strong>Create</strong></a> - add a vinyl</li>
	<li><a href="vinyls/read.php"><strong>Find</strong></a> - find a vinyl</li>
	<li><a href="vinyls/update.php"><strong>Edit</strong></a> - edit a vinyl</li>
</ul>
<h2 class="">Lists</h2>
Music Listened
<ul>
	<li><a href="music-listened/create.php"><strong>Create</strong></a> - add a song</li>
	<li><a href="music-listened/read.php"><strong>Find</strong></a> - find a song</li>
	<li><a href="music-listened/update.php"><strong>View/edit</strong></a> - edit a song</li>
</ul>

<?php include "./templates/footer.php"; ?>