---
Title: I Have a Blog Roll on My Website
TitleSlug: blogroll available
Published: 2023-12-21 03:09:49
Author: Pablo Morales
Layout: blog
Tag: blog roll, blogs, indieweb, interwebs
Description: I created a blog roll
Image: https://images.unsplash.com/photo-1517816428104-797678c7cf0c?q=80&w=2940&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D
ImageAlt: horn
---
I've heard of blogrolls on and off. I didn't really think much of them until recently. I saw Tracy's post on [Building community out of strangers](https://tracydurnell.com/2023/11/30/building-community-out-of-strangers/). I really enjoyed this post and it got me really interested in exploring the idea of blog rolls. I really enjoy the focus on community and connections. I'm always trying to learn from others and see how I can make deeper connections with others even if they don't know it. It's a different way of connecting with other humans.

I like how Tracy writes what I think often when I read people's blogs

>*Yes, I want to see what you ate for lunch.*
>
>*Yes, I want your snarky take on this week’s tech culture kerfuffle.*
>
>*Yes, I want to hear the song you’ve had stuck in your head all week.*

I really want to know what exciting things you are doing! 


So I created a blog roll! This is how I did it! 

I exported the OPML file downloaded from FreshRSS to convert it to a CSV file. I converted the file and removed unnecessary columns. These are the fields I kept in the CSV file:

* Name of Feed
* Site URL 
* Feed URL

Then I used the concatenate feature in Excel. I don't mind practicing the use of Excel formulas. It was actually fun to create and format the links in markdown in the way I wanted. If I decide to change the formatting later, I could always change up the concatenate formula I have setup. I used this formula to create markdown from various fields into this format:

``
=CONCATENATE("* ", "[",  A3, "](", B3, ")", " ", "[(RSS)]", "(", C3, ")")
``

An example of this would be:

``
* [Alex Sirac](https://alexsirac.com/) [(RSS)](https://alexsirac.com/feed/)
``


Which gives me a list item:

* [Alex Sirac](https://alexsirac.com/) [(RSS)](https://alexsirac.com/feed/)
* [benji](https://benji.dog/) [(RSS)](https://www.benji.dog/feed.xml)

My blog roll is available [here](roll). I will make it prettier later. I might try to implement a database version so it is easier to maintain. That's another thing to add to the to-list.