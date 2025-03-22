---
Title: Implementing Indielogin to my site and microsub compatible.
TitleSlug: I added Indielogin to my site and making it h-entry compatible.
Published: 2023-03-19 02:27:47
Author: Pablo Morales
Layout: blog
Tag: decentralized login, indieauth, indielogin, indieweb, w3c, open source
---

[image 797b544f40d54119.png]

## Intro
I've been on this SSO/oauth rabbit hole these last few weeks. I have implemented vouch-proxy (blog post coming soon, I promise. pls don't hate me! ) I happened to stumble upon indieauth which leadme to indielogin, and so forth continuing my rabbit hole. I heard about it this many years ago but it placed on my, "I know I should look into this soon" filing cabinet. Queue the Spongebob scene. 

Indie Web states the following:

The IndieWeb effort is different from previous efforts/communities:

* Principles over project-centrism. Others assume a monoculture of one project for all. We are developing a plurality of projects. The IndieWeb community has a code-of-conduct.
* Publish on your site instead of emailing a list. Show before tell. Prioritize by making what you need, creating, iterating on your own site.
* Design first, protocols & formats second. Focus on good UX & using your own prototype features to create minimum necessary formats & protocols.


I would like to give a shoutout to the [IndieWeb Community](https://indieweb.org/Getting_Started) and [Aaron Parecki](https://aaronparecki.com/) all that they do. You guys provide so much information. It wouldn't have been possible for this.

Disclamer: By no means is my setup even close to being perfect. I'm still a new to this. 

The premise here is that you use your domain. Mine for example:


`https://lifeofpablo.com`

## IndieAuth
**[IndieAuth](https://indieauth.net/)** is a federated login protocol for Web sign-in, enabling users to use their own domain to sign in to other sites and services. IndieAuth can be used to implement OAuth2 login AKA OAuth-based login. 

Here are a few examples of sites that use indieauth:

[gallery indieauth-examples indieauth zoom 328 428]

## Selfauth

On my site I implemented, [selfauth](https://indieweb.org/selfauth). Selfauth is a single user authorization endpoint written as single-file PHP without a database. For common indielogin/indieauth, it usually requires putting a <link..... rel="me> in my header pointing to Twitter <i class="fa-brands fa-twitter"></i> or Github <i class="fa-brands fa-github"></i> to help identify me and use as a method of authenticating my domain. I instead used selfauth and used a <link> to a directory on my server to use as an authorization_endpoint as shown below.

`<link rel="authorization_endpoint" href="https://lifeofpablo.com/auth/" />`

So now I can login to any website that authenticates users using indielogin/indieauth 

## Adding h-entry microformats2

**[h-entry](http://microformats.org/wiki/h-entry)** is the microformats2 vocabulary for marking up blog posts on web sites. It can also be used to mark-up any other episodic or time series based content.

Today, I  added h-entry tags to my [homepage](https://lifeofpablo.com) and to my [blog](https://lifeofpablo.com/blog).  H-entry tags are simply `html classes` added to a specific locations on a page. I am using [Datenstrom Yellow.](https://datenstrom.se/yellow/) I used their [API](https://datenstrom.se/yellow/help/api-for-developers) and the blog layouts ([blog-start.html](https://github.com/annaesvensson/yellow-blog/blob/main/blog-start.html) & [blog.html](https://github.com/annaesvensson/yellow-blog/blob/main/blog.html) )

Your h-entries should have, at minimum, the following properties:

`e-content` 

* the main content of the post

`p-name` 

* if your post is an article with a name, use this classname.

`dt-published `

* the datetime the post was published at, in ISO8601 format, with a timezone
* Datenstrom Yellow does this already in this format

`u-url `

* the canonical URL of the post, especially important on pages listing multiple posts

*[Here is a full list of h-entry tags ](https://microformats.org/wiki/h-entry)*

Use this [site](https://indiewebify.me/validate-h-card/) to help you validate and make sure the information is pulling correctly. Adjust your code accordingly.

Here is my code reflecting h-entry tags as classes on Datenstrom, (the flat-file cms this site is running on).

*I recommend using* `<span></span>`  *where you need to place two or h-entry tags on the same line. It will help prevent unnecessary line breaks. Datenstrom, for example puts the date and author in the same line. (Look at example below and then look what I do in my code)*

`2023-03-19 by Pablo Morales`

* [**blog-start.html code:**](https://gist.github.com/pmoralesgarcia/7be6da3f4a0914388d1c1d53d9c9644c)

* [**blog.html code:**](https://gist.github.com/pmoralesgarcia/3e7e60c8767d186801dadc0dba6fd29a)



## Webmention.io [image webmention-logo-380.png Webmention.io 64 64]

<p><span class="p-summary"><b><dfn><a class="external text" href="https://www.w3.org/TR/webmention/">Webmention</a></dfn></b> is an open web standard (<a href="/W3C" title="W3C">W3C</a> Recommendation) for conversations and interactions across the web, a powerful building block used for a growing distributed network of peer-to-peer <a href="/comment" title="comment">comments</a>, <a href="/like" title="like">likes</a>, <a href="/repost" title="repost">reposts</a>, and other <a href="/responses" title="responses">responses</a> across the web.</span>
</p>

This is the part where I start losing it a bit. [Webmention.io](https://webmentions.io) is pretty straight forward. I have started experimenting with cross-site conversations. Luckly [Webmentions.io](https://webmentions.io) helps me with this. 

Basically, you sign up using your domain and configure your website with the appropriate steps to webmentions.io to start allowing you to do cross-site interactions. 

The steps are 

* Sign up using your domain
* Setup
* Get your mention feeds
* Get your API key.


## Joining the community and attending events

There seems to be a big community! I am for sure commited! I will continue to share and contribute to this community. I am always looking to find new people to share things I enjoy.

 I am really excited to join my first event virtually. I am going to join the "Homebrew Website Club" this week and meet some cool people! Want to join ? [Here is the link](https://events.indieweb.org/2023/03/homebrew-website-club-pacific-fh3p1KDFqJVa). 

Here's the link to the entire calendar. [Upcoming Events](https://events.indieweb.org/)

## Conclusion
* That's it!  We covered:
* Indieauth
* Selfauth
* h-entry tags & code examples
* Webmention.io
* Joining the community

I didn't go too much in detail but it's a start. I am happy to continue experimenting with this protocal and implementation for an open-web. Rabbit holes like this are fun and push my limits! There are so many options on how you can implement indieauth and how you can process your data. It's easier for anyone who is using a popular platform such as Wordpress. Why not try the non-easy route? It's fun!


Happy authenticating!

- *Pablo*





