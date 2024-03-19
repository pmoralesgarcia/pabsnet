---
Title: Displaying Webmentions Extension for Datenstrom Yellow
Published: 2024-03-18 20:11:49
Author: Pablo Morales
Layout: blog
Tag: 2024, GitHub, webmention, Datenstrom, Datenstrom Yellow, development, extensions
Description: I built a extension to display webmentions for Datenstrom Yellow.
Image: https://github.com/pmoralesgarcia/yellow-webmention/raw/main/screenshot.png
ImageAlt: screenshot of webmentions
---
<img src="https://github.com/pmoralesgarcia/yellow-webmention/raw/main/screenshot.png" alt="Screenshot" class="measure center w-60 pb-2">

<p class="measure center w-60 pb-2">A screenshot of webmentions being displayed using the webmention extension for Datenstrom Yellow</p>

The other night I was helping my friend [Mani](https://manic.so/) setup [webmentions](https://indieweb.org/Webmention) on his website since he is building his website and I was hoping to build something while I was helping him. 
He got me thinking, "I should really do something on your website with webmentions!" I also said to myself, "I will do something about it!" As cheesy as that sounds, it was a good motivational moment to get something done. 

I guess that was the night to start back something I had the skeleton started, well at least partially. Sometime last year I started the skeleton. 

**Well I did it!** 

I built a extension displaying webmentions on my website.  This is something I had started sometime last year and I finally got around to do it again. I actually had a repository for it on Github. Since I didn't touch it for so long, I had also noticed that [Robert](https://frittiert.es/) had make an issue on the repository asking if I had made the webmention extension working. Robert also uses Datenstrom Yellow He wrote this back in January. 

I would like the IndieWeb, indieauth, webmentions, micropub, etc. to be more present in the Datenstrom community. I am hopeful that more people down the road will use Datenstrom and me more inclined to create more plugins for it.. 

The extension is made for the flat-file CMS (content management system) call [Datenstrom Yellow](https://datenstrom.se/yellow/) or simply Datenstrom. This is the CMS of choise my personal website. For the longest time  I had hard-coded (I'm phasing this out) the displaying of webmentions on my website since I wanted to have some implemention of displaying webmentions. Creating this extension may not be a big deal to others but I feel it's a big leap for me as I get more comfortable getting back into modifying the tools I use daily. I would like to help contribute more the the communities I participate in such as the Datenstrom Community. Creating this extension will help me give back and tippy toe back into the developmental world. 

With that being said new to building extensions for a CMS. Having lingered this idea for some time, I took a look at the API. As I started to build this extension, Datenstrom provides good documentation overall.  There are plenty examples to utiilize the [API](https://datenstrom.se/yellow/help/api-for-developers) used to interact with the CMS. You would think that me using this CMS for so long I would be doing more with it? I guess I finally go the itch. I'm still learning the ins and outs of it as I get into the more advanced features. 

The main files that make the extension work ([GitHub Repo](https://github.com/pmoralesgarcia/yellow-webmention)). So here is how it works!

* [`extension.ini `](https://github.com/pmoralesgarcia/yellow-webmention/blob/main/extension.ini) - tells Datenstrom what to do with the files and adds extension config paramenters to `yellow-system.ini ` (config file for Datenstrom).
* [`icon.css` ](https://github.com/pmoralesgarcia/yellow-webmention/blob/main/icon.css)- A CSS file for Material Icons from Google Fonts.
* [`webmention.css`](https://github.com/pmoralesgarcia/yellow-webmention/blob/main/webmention.css) - A CSS file to style the webmention extension.
* [`webmention.js `](https://github.com/pmoralesgarcia/yellow-webmention/blob/main/webmention.js)- A javascript file to parse webmentions from [webmention.io](https://webmention.io/) into HTML and it pulls the webmentions for the specific page you are on.
* [`webmention.php` ](https://github.com/pmoralesgarcia/yellow-webmention/blob/main/webmention.php)- This tells Datenstrom how to interact with the API and how to activate the extension.

This extension is still a work in progress. There is so much to do still. Please bear with me as I make the code, especially the javascript, more readable and more optimized. I also know I repeat myself. It at least functions to get started. I'll be updating this code throughout the next few days and as needed down the road. 

If you are interested in collaborating, I'd love to connect or simply create a pull request on the [Github repository](https://github.com/pmoralesgarcia/yellow-webmention). Do you or someone use Datenstrom as the CMS of choice? I'd love to know! 

To learn more about Datenstrom visit their [website](https://datenstrom.se/yellow/) or the [GitHub](https://github.com/datenstrom/yellow) repository. 

<a href="https://news.indieweb.org/en" class="u-syndication">
  Also posted on IndieNews. 
</a>
