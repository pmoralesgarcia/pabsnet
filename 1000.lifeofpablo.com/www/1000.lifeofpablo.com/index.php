<!DOCTYPE html>
<html>
    <head>
        <title>New Albums I've Listened Too</title>
        <meta charset="utf-8">
        <meta name="author" content="Pablo Morales">
        <link rel="stylesheet" href="https://unpkg.com/tachyons@4.12.0/css/tachyons.min.css"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="webmention" href="https://webmention.io/lifeofpablo.com/webmention">
    </head>
    <body>
        <nav class="dt w-100 border-box pa3 ph5-ns baskerville">
            <a class="dtc v-mid mid-gray link dim w-25" href="https://lifeofpablo.com/" title="Home">
              <img src="https://static.lifeofpablo.com/pabs-cropped.jpg" class="dib w2 h2 br-100" alt="Pablo Morales">
            </a>
            <div class="dtc v-mid w-75 tr baskerville">
              <a class="link dim dark-gray f6 f5-ns dib mr3 mr4-ns" href="https://lifeofpablo.com/" title="About">Main Site</a>
              <a class="link dim dark-gray f6 f5-ns dib mr3 mr4-ns" href="https://lifeofpablo.com/blog/" title="Store">Blog</a>
              <a class="link dim dark-gray f6 f5-ns dib" href="https://lifeofpablo.com/linkinbio" title="Contact">Links</a>
            </div>
          </nav>
          
          
          <?php
          $catalog_json = file_get_contents('albums.json');
          
          $decoded_json = json_decode($catalog_json, true);
          
          $items = $decoded_json['albums'];
          
          foreach($items as $item) {
            $id = $item['id'];
            $name = $item['title'];
          
            echo $id;

            ?>

        <article class="bg-white">
            <div class="vh-75 cover bg-center" style="background-image: url(https://images.unsplash.com/photo-1483412033650-1015ddeb83d1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2946&q=80);"></div>
            <div class="ph4 ph5-m ph6-l">
              <div class="pv5 f4 f2-ns measure center">
                <h1 class="fw6 f1 fl w-100 black-70 mt0 mb3 avenir">1000 Record Covers</h1>
                <p class="db lh-copy black-70 serif fw1 mv0 f4 f3-m f2-l measure baskerville">
                  I've been stuck in this loop of music where I listen to the same stuff over and over and over again. It's been something I've been disatisfied with myself for a while. So I decided to pick up a book, "1000 Record Covers" at my local bookstore. This was an accident. It just happened to come across my browsing. Here I am posting and learning about new (to me) music and expanding on my taste. 
                </p>
             <p class="db lh-copy black-70 serif fw1 mv0 f4 f3-m f2-l measure baskerville">
I made it a challenge to listen to 1000 albums. I will listen to one new album daily and get through the entire book in 2.75 years as long as do it daily. 
             </p>

                <p><a class="underline black-70 hover-orange" href="https://lifeofpablo.com/blog/one-thousand-albums-in-one-thousand-days">Original Blog Post</a></p>
              </div>
              </div>
        <section class="cf w-100 pa2-ns baskerville">
            <h1 class="center f3 avenir">50's</h1>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://static.lifeofpablo.com/media/images/blog-images/hen-gates/hen-gates.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Let's Go Dancing To Rock And Roll</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Hen Gates And His Gaters</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.discogs.com/wxiJK8BD86nVvQzimuu7R5nvLuhjgW4ww-MGZBURzMQ/rs:fit/g:sm/q:90/h:600/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTYxNzA1/OTctMTQ1MzY3NDEy/OC00Mzk0LmpwZWc.jpeg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db baskerville">
                <h3 class="f5 f4-ns mb0 black-90">Dance the Rock & Roll</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Various Artists</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.ebayimg.com/images/g/42AAAOSwLJRjmUJx/s-l1600.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Rock 'n' Roll with the Robins</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">The Robins</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.discogs.com/VhOrWc15ZhQpX09i1Jfr8YYNUeQ9yacq9Jap6eTRHY0/rs:fit/g:sm/q:90/h:571/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTU4MTkx/OTYtMTQwMzYyNTQ5/Ny01MTM3LmpwZWc.jpeg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">No One Cares</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Frank Sinatra</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.discogs.com/wfczpgQAIiUh8dOQa7av7WgJnE3sw63OJUZRoaQRrvE/rs:fit/g:sm/q:90/h:590/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTIzODcy/NjgtMTM5NjY2Nzc1/Mi0xNjYyLmpwZWc.jpeg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Just One of Those Things</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Nat "King" Cole</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.discogs.com/PVWX6JLHQ7xYvnNVY2rCMAiphyAW5e0rkao3T7OXFbk/rs:fit/g:sm/q:90/h:308/w:315/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTcwOTE2/Ni0xMTUwMzkzMTk2/LmpwZWc.jpeg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Bing With A Beat</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Bing Crosby</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://i.discogs.com/8tg0aRZf87TaprtNv0GjriMcV2dUyOH-qG3z_3UZJTE/rs:fit/g:sm/q:90/h:220/w:220/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTI1NDA1/MjctMTI4OTUyNjY3/My5qcGVn.jpeg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">So Smooth</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Perry Como</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://upload.wikimedia.org/wikipedia/en/f/f8/Olealalee.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Olé ala Lee</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Peggy Lee</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0011.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0012.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0013.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0014.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0015.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0016.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0017.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0018.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0019.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0020.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0021.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <article class="fl w-100 w-50-m  w-25-ns pa2-ns">
              <div class="aspect-ratio aspect-ratio--1x1">
                <img style="background-image:url(https://mrmrs.github.io/images/0022.jpg);" 
                class="db bg-center cover aspect-ratio--object" />
              </div>
              <a href="#0" class="ph2 ph0-ns pb3 link db">
                <h3 class="f5 f4-ns mb0 black-90">Title of piece</h3>
                <h3 class="f6 f5 fw4 mt2 black-60">Subtitle of piece</h3>
              </a>
            </article>
            <h1 class="fw6 f3 avenir">60's</h1>
          </section>
          
          <footer class="pv4 ph3 ph5-m ph6-l mid-gray baskerville">
            <small class="f6 db tc">© 2023 <b class="ttu">Pabs Tech, LLC</b>., All Rights Reserved</small>
            <div class="tc mt3">
              <a href="https://lifeofpablo.com/" title="Home" class="f6 dib ph2 link mid-gray dim">Pablo Morales</a>
              <a href="https://lifeofpablo.com/cookie-policy/"    title="Cookies" class="f6 dib ph2 link mid-gray dim">Terms of Use</a>
              <a href="https://lifeofpablo.com/privacy/"  title="Privacy" class="f6 dib ph2 link mid-gray dim">Privacy</a>
            </div>
          </footer>
          
          
          
    </body>
</html>
