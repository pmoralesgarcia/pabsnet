---
Title: Content available in other languages
Published: 2023-06-14 22:29:33
Author: Pablo Morales
Layout: blog
Tag: writing in other languages, french, français, indieweb, webmentions, localization, English, h-entry, h-entry tags
Modified: 2023-06-14 23:29:33
Image: https://images.unsplash.com/photo-1489945052260-4f21c52268b9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80
---
[image https://images.unsplash.com/photo-1489945052260-4f21c52268b9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80]

<a markdown="1" rel="alternate" href="/blog/le-contenu-disponible-dans-d-autre-langues" hreflang="en"> **Read Blog Post en français.** </a>

Once a moon, I'll write in French, During our last [Indieweb Meeting](https://events.indieweb.org/2023/06/homebrew-website-club-pacific-wephspCwOHj2), we discussed, ["Multilngual Brainstorming"]() related to [h-entry tags](https://microformats.org/wiki/h-entry). 


Let's talk about this briefly!

If we must bring up the subject, content localization goes hand in hand.  

The main question is, "How do I present content in different languages on my website" 

It is important to use , `href` to select the ISO two-letter abbreviation of the intended language to be used. For this example below, we would use Spanish. The ISO code would be  **ES**. The link must point to the version of the content in the intended language . 

For Example: 
<div markdown="1">  
``` html

<a rel="alternate" href=""  hreflang="[language abbreviation]">[link content]</a>
```
</div>
<br>
Example using`rel="alternate" ' with Spanish as the selected language option. 
``` php
<html lang="en">

<article class="h-entry">
  <h1 class="p-name"> <a href="/in-english" class="u-url">Article in English</a> </h1>
  Read <a rel="alternate" href="/en-espanol" hreflang="es">Article en Espanol</a>
</article>
```

  <div class="h-entry">
<span> <a class="u-in-reply-to" href="https://gregorlove.com/">gRegor Morrill</a></span>
    <p class="e-content"> Thank you for getting me up to speed with this topic and for providing me some sweet examples. It will make life easier. ! c</p>
  </div>


Some links <i class="fa-solid fa-link"></i>:

* https://microformats.org/wiki/hreflang

* https://php.microformats.io/?id=20230615025535798

* https://php.microformats.io/?id=20230615024902815

* https://microformats.org/wiki/h-entry

* https://microformats.org/wiki/multilingual-examples


<html lang="en">

<style>
.webmention {
  display: flex;
  padding-top: 10px;
}

.wm_info {
  display: flex;
  flex-direction: column;
}

.m_author {
  font-size: 0.8rem;
  text-decoration: none;
  color: black;
}
.m_published {
  font-size: 0.8rem;
}

.wm_summary {
  font-size: 0.8rem;
}

.menicons {
  display: flex;
  flex-direction: row;
  align-items: center;
  padding-left: 0px;
}
.micon {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  padding-right: 15px;
}
</style>
