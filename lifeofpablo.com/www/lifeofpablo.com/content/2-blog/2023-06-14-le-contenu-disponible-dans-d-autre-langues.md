---
Title: Le contenu disponible dans d'autre langues
Published: 2023-06-14 22:12:17
Author: Pablo Morales
Layout: blog
Tag: french, blog post in french, français, dialogue, localization, hreflang, iso, h-tag, h-tags, indieweb, webmentions, country codes, FR, ES, EN, html
Modified: 2023-06-14 23:29:33
Image: https://images.unsplash.com/photo-1489945052260-4f21c52268b9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80
---
[image https://images.unsplash.com/photo-1489945052260-4f21c52268b9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80]
 <a markdown="1"rel="alternate" href="/blog/content-available-in-other-languages" hreflang="en">**Read Blog Post in English**</a>

Normalement, j'écris en anglais. Pendant la dernière réunion d'IndieWeb, on a discuté le "multilingual-brainstorming" avec les [balises ou catégorisation (tags en anglais) "h-entry.](https://microformats.org/wiki/h-entry)" 

C'est parti !

C'est important pour la localization . 

La question présentais, "Comment presenter le contenu des blog posts en langues different ?" 

Il faut utiliser la balise, `href` pour la selection de la langue en format ISO - Code des langues. Par exemple, l'espagnol sera le code, **ES**. Il est important d'avoir un lien où le contenu est disponible dans la langue prévue. 

Par Example:   
``` html

<a rel="alternate" href=""  hreflang="[abréviation du langue]">[contenu du lien]</a>
```
<br>
Example avec `rel="alternate"` qu’utilise l'espagnol. 
``` php
<html lang="en">

<article class="h-entry">
  <h1 class="p-name"> <a href="/in-english" class="u-url">Article in English</a> </h1>
  Read <a rel="alternate" href="/en-espanol" hreflang="es">Article en Espanol</a>
</article>
```

  <div class="h-entry">
<span> <a class="u-in-reply-to" href="https://gregorlove.com/">gRegor Morrill</a></span>
    <p class="e-content"> Merci 1000 fois pour t'assistance. Je suis mis à jour!</p>
  </div>



Some Links <i class="fa-solid fa-link"></i>:

* https://microformats.org/wiki/hreflang

* https://php.microformats.io/?id=20230615025535798

* https://php.microformats.io/?id=20230615024902815

* https://microformats.org/wiki/h-entry

* https://microformats.org/wiki/multilingual-except 


