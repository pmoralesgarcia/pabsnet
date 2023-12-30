---
Title: Using JSON on My Site
Published: 2023-12-29 21:29:49
Author: Pablo Morales
Layout: blog
Tag: JSON, lists, movies, 
Description: JSON to work smarter (maybe)
Image: 
ImageAlt: 
---
This past week, I've been learning to use JSON on my website. As I am learning to implement a Micropub Server on my site, I feel that I should learn how JSON works. In a way JSON has been used on my site before by using microformats in the rendered HTML code and being read by other sites and services. I've been wanting to explore ways to update content on my site in a more effective manner instead of having to manually update html code for each entry of a list. An example of this would be a list of movies I watched. This is still a a learning process. I have so much to learn. I want to expand [Datenstrom Yellow](https://github.com/datenstrom/yellow), the flat-file c I am using, to still be simple but be powerful at the same time. 

An issue I have to work out for longevity and simplicity is by creating an extension for the different lists. Right now I have to create a layout for a particular list such as ```movies```, create a new markdown page in my content folder, and finally set the desired layout (template). An example of this would be ```Layout: movies```. This isn't ideal when I write a blog post. If I changed the layout of a blog post, it wouldn't be recognized as a blog post in the main blog feed. That's where an extension would come in place and place it in the markdown file of the blog post or any other page I'd like to create on my website. This wouldn't require to change the layout. In the markdown file, I could simply place, ```[list 1]``` or even simply, ```[movies]``` to show the list. Coming Soon!

### Example Code:

Here's the layout for ```movies.html```. I use PHP to create the reoccuring HTML elements automatically when I modify the movie JSON file. It will update the list accordingly. 



``` html
<?php $this->yellow->layout("header") ?>
<div class="content">
<div class="main h-entry" role="main">
<h1><?php echo $this->yellow->page->getHtml("titleContent") ?></h1>
<div class="entry-content e-content"><?php echo $this->yellow->page->getContentHtml() ?></div>
<div class="mw6 center">
<?php
$catalog_json = file_get_contents('media/downloads/movies.json');

$decoded_json = json_decode($catalog_json, true);

$items = $decoded_json['movies'];

foreach($items as $item) {
	$id = $item['id'];
  $name = $item['title'];
	$cover = $item['cover'];
  $info_url = $item['info_url'];



  
    echo '<article class="h-item">';
    echo '<a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="'.$info_url.'">';
    echo '<div class="dtc w4">';
    echo '<img src=" '.$cover. '" class="db w-100 u-photo" alt="movie poster of '.$name.'"/>';
    echo '</div>';
    echo '<div class="dtc v-top pl2">';
    echo '<h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">'.$name.'</h1>';
    echo '<dl class="mt2 f6">';
    echo '<dt class="clip">ID</dt>';
    echo '<dd class="ml0">ID: '.$id.'</dd>';
    echo '</dl>';
    echo '</div>';
    echo '</a>';
    echo '</article>';


}
?>
</div>

<div class="permalink">Permalink: <a class="u-url" href="https://lifeofpablo.com<?php echo $this->yellow->page->getLocation($absoluteLocation = false) ?>">https://lifeofpablo.com<?php echo $this->yellow->page->getLocation($absoluteLocation = false) ?></a>
         </div>
</div>
</div>
<?php $this->yellow->layout("footer") ?>



```
JSON

This is the example movie JSON (movies.json) code for the movies I watched in 2023. As I CRUD (create, read, update, delete) entries, the PHP code will parse the code with the latest version of the JSON file.

``` json
{
"movies": [
	{
		"id" : 0,
		"title" : "Barbie",
		"cover" : "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTps15LVPBd4bN4dWOYwJ1ggcNE_4tzLnR10qZlngWbwQ4G-cgE",
		"info_url" : "https://www.themoviedb.org/movie/346698-barbie?language=en-US"
	
	},
	{
		"id" : 1,
		"title" : "The Hunger Games: The Ballad of Songbirds & Snakes",
		"cover" : "https://encrypted-tbn2.gstatic.com/images?q=tbn:ANd9GcRvwP_lNaUz46LtcewMX0xdKd4bT-8hAHaAt8HjB96tLsMolVVT",
		"info_url" : "https://www.themoviedb.org/movie/695721-the-hunger-games-the-ballad-of-songbirds-snakes?language=en-US"
	
	},
	{
		"id" : 2,
		"title" : "Shrek 2",
		"cover" : "https://encrypted-tbn1.gstatic.com/images?q=tbn:ANd9GcSFADKf7LWrP_r2PkPMo4zVxAdgoUK4KMtgRsfMSUTpisbpGG7N",
		"info_url" : "https://www.themoviedb.org/movie/809-shrek-2?language=en-US"
	
	},
	{
		"id" : 3,
		"title" : "Killers of the Flower Moon",
		"cover" : "https://upload.wikimedia.org/wikipedia/en/8/88/Killers_of_the_Flower_Moon_film_poster.jpg",
		"info_url" : "https://www.themoviedb.org/movie/466420-killers-of-the-flower-moon?language=en-US"
	
	},
	{
		"id" : 4,
		"title" : "Doctor Strange in the Multiverse of Madness",
		"cover" : "https://upload.wikimedia.org/wikipedia/en/1/17/Doctor_Strange_in_the_Multiverse_of_Madness_poster.jpg",
		"info_url" : "https://www.themoviedb.org/movie/453395-doctor-strange-in-the-multiverse-of-madness?language=en-US"
	
	},
	{
		"id" : 5,
		"title" : "The Unbearable Weight of Massive Talent",
		"cover" : "https://www.themoviedb.org/t/p/w1280/8JzPuj4lNQv0wmd38u0ee1dxzhn.jpg",
		"info_url" : "https://www.themoviedb.org/movie/648579-the-unbearable-weight-of-massive-talent?language=en-US"
	
	},
	{
		"id" : 6,
		"title" : "Sonic the Hedgehog 2",
		"cover" : "https://www.themoviedb.org/t/p/w1280/6DrHO1jr3qVrViUO6s6kFiAGM7.jpg",
		"info_url" : "https://www.themoviedb.org/movie/675353-sonic-the-hedgehog-2?language=en-US"
	
	},
	{
		"id" : 7,
		"title" : "It's Still Your Bed",
		"cover" : "https://www.themoviedb.org/t/p/w1280/wtHBxOisTtU10ieNoXHX62kB613.jpg",
		"info_url" : "https://www.themoviedb.org/movie/613027-it-s-still-your-bed?language=en-US"
	
	},
	{
		"id" : 8,
		"title" : "Dog",
		"cover" : "https://www.themoviedb.org/t/p/w1280/rkpLvPDe0ZE62buUS32exdNr7zD.jpg",
		"info_url" : "https://www.themoviedb.org/movie/626735-dog?language=en-US"
	
	},
	{
		"id" : 9,
		"title" : "Uncharted",
		"cover" : "https://www.themoviedb.org/t/p/w1280/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg",
		"info_url" : "https://www.themoviedb.org/movie/335787-uncharted?language=en-US"
	}
	
]}

```

This JSON file with the help of PHP to render HTML content:

<div class="mw6 center">
<article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/346698-barbie?language=en-US"><div class="dtc w4"><img src=" https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTps15LVPBd4bN4dWOYwJ1ggcNE_4tzLnR10qZlngWbwQ4G-cgE" class="db w-100 u-photo" alt="movie poster of Barbie"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Barbie</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 0</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/695721-the-hunger-games-the-ballad-of-songbirds-snakes?language=en-US"><div class="dtc w4"><img src=" https://encrypted-tbn2.gstatic.com/images?q=tbn:ANd9GcRvwP_lNaUz46LtcewMX0xdKd4bT-8hAHaAt8HjB96tLsMolVVT" class="db w-100 u-photo" alt="movie poster of The Hunger Games: The Ballad of Songbirds &amp; Snakes"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">The Hunger Games: The Ballad of Songbirds &amp; Snakes</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 1</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/809-shrek-2?language=en-US"><div class="dtc w4"><img src=" https://encrypted-tbn1.gstatic.com/images?q=tbn:ANd9GcSFADKf7LWrP_r2PkPMo4zVxAdgoUK4KMtgRsfMSUTpisbpGG7N" class="db w-100 u-photo" alt="movie poster of Shrek 2"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Shrek 2</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 2</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/466420-killers-of-the-flower-moon?language=en-US"><div class="dtc w4"><img src=" https://upload.wikimedia.org/wikipedia/en/8/88/Killers_of_the_Flower_Moon_film_poster.jpg" class="db w-100 u-photo" alt="movie poster of Killers of the Flower Moon"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Killers of the Flower Moon</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 3</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/453395-doctor-strange-in-the-multiverse-of-madness?language=en-US"><div class="dtc w4"><img src=" https://upload.wikimedia.org/wikipedia/en/1/17/Doctor_Strange_in_the_Multiverse_of_Madness_poster.jpg" class="db w-100 u-photo" alt="movie poster of Doctor Strange in the Multiverse of Madness"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Doctor Strange in the Multiverse of Madness</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 4</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/648579-the-unbearable-weight-of-massive-talent?language=en-US"><div class="dtc w4"><img src=" https://www.themoviedb.org/t/p/w1280/8JzPuj4lNQv0wmd38u0ee1dxzhn.jpg" class="db w-100 u-photo" alt="movie poster of The Unbearable Weight of Massive Talent"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">The Unbearable Weight of Massive Talent</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 5</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/675353-sonic-the-hedgehog-2?language=en-US"><div class="dtc w4"><img src=" https://www.themoviedb.org/t/p/w1280/6DrHO1jr3qVrViUO6s6kFiAGM7.jpg" class="db w-100 u-photo" alt="movie poster of Sonic the Hedgehog 2"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Sonic the Hedgehog 2</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 6</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/613027-it-s-still-your-bed?language=en-US"><div class="dtc w4"><img src=" https://www.themoviedb.org/t/p/w1280/wtHBxOisTtU10ieNoXHX62kB613.jpg" class="db w-100 u-photo" alt="movie poster of It's Still Your Bed"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">It's Still Your Bed</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 7</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/626735-dog?language=en-US"><div class="dtc w4"><img src=" https://www.themoviedb.org/t/p/w1280/rkpLvPDe0ZE62buUS32exdNr7zD.jpg" class="db w-100 u-photo" alt="movie poster of Dog"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Dog</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 8</dd></dl></div></a></article><article class="h-item"><a class="link dt w-100 bb b--black-10 pb2 mt2 dim blue u-url" href="https://www.themoviedb.org/movie/335787-uncharted?language=en-US"><div class="dtc w4"><img src=" https://www.themoviedb.org/t/p/w1280/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg" class="db w-100 u-photo" alt="movie poster of Uncharted"></div><div class="dtc v-top pl2"><h1 class="f6 f5-ns fw6 lh-title black mv0 p-name">Uncharted</h1><dl class="mt2 f6"><dt class="clip">ID</dt><dd class="ml0">ID: 9</dd></dl></div></a></article></div>

  

There's a lot to do so far. I am hopeful I will get my website where it needs to be for ~~2023~~ 2024. 