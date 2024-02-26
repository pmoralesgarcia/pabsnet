<?php

/**
 * Use an HTML form to edit an entry in the
 * users table.
 *
 */

 require "../../config-lists.php";
 require "../../common.php";

if (isset($_POST['submit'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die();

  try {
    $connection = new PDO($dsn, $username, $password, $options);

    $user =[
      "title" => $_POST['title'],
      "artist"  => $_POST['artist'],
      "location"     => $_POST['location']
    ];

    $sql = "UPDATE music_listened 
            SET title = :title, 
              artist = :artist, 
              location = :location
            WHERE id = :id";
  
  $statement = $connection->prepare($sql);
  $statement->execute($user);
  } catch(PDOException $error) {
      echo $sql . "<br>" . $error->getMessage();
  }
}
  
if (isset($_GET['id'])) {
  try {
    $connection = new PDO($dsn, $username, $password, $options);
    $id = $_GET['id'];

    $sql = "SELECT * FROM music_listened WHERE id = :id";
    $statement = $connection->prepare($sql);
    $statement->bindValue(':id', $id);
    $statement->execute();
    
    $user = $statement->fetch(PDO::FETCH_ASSOC);
  } catch(PDOException $error) {
      echo $sql . "<br>" . $error->getMessage();
  }
} else {
    echo "Something went wrong!";
    exit;
}
?>

<?php require "../templates/header.php"; ?>

<?php if (isset($_POST['submit']) && $statement) : ?>
	<blockquote><?php echo escape($_POST['title']); ?> successfully updated.</blockquote>
<?php endif; ?>

<h2>Edit a vinyl</h2>

<form class="pa4 black-80" method="post">
  <div class="measure">
    <input name="csrf" type="hidden" value="<?php echo escape($_SESSION['csrf']); ?>">
    <?php foreach ($user as $key => $value) : ?>
      <label class="f6 b db mb2" for="<?php echo $key; ?>"><?php echo ucfirst($key); ?></label>
	    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="<?php echo escape($value); ?>" <?php echo ($key === 'id' ? 'readonly' : null); ?>>
    <?php endforeach; ?> 
    <input class="b ph3 pv2 input-reset ba b--black bg-transparent grow pointer f6 dib" type="submit" name="submit" value="Submit">
</form>


<a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
