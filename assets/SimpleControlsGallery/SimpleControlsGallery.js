//GEO http://www.dynamicdrive.com/dynamicindex4/simplegallery.htm
//** Simple Controls Gallery- (c) Dynamic Drive DHTML code library: http://www.dynamicdrive.com
//** Dec 7th, 08'- Script created
//** February 6th, 09'- Updated to v 1.3:
	//1) Adds Description Panel to optionally show a textual description for each slide
	//2) In Auto Play mode, you can now set the number of cycles before gallery stops.
	//3) Inside oninit() and onslide(), keyword "this" now references the current gallery instance

//** May 16th, 11'- Updated to v 1.4: Adds ability to show image gallery only after all images within gallery has been loaded. Requires jQuery 1.5+

var simpleGallery_navpanel={
	loadinggif: 'styles/chemineur/theme/diapo/wait.gif', //full path or URL to loading gif image
	panel: {height:'45px', opacity:0.5, paddingTop:'5px', fontStyle:'bold 11px Verdana'}, //customize nav panel container
	images: [ 'styles/chemineur/theme/diapo/left.gif', 'styles/chemineur/theme/diapo/play.gif', 'styles/chemineur/theme/diapo/right.gif', 'styles/chemineur/theme/diapo/pause.gif'], //nav panel images (in that order)
	imageSpacing: {offsetTop:[-4, 0, -4], spacing:10}, //top offset of left, play, and right images, PLUS spacing between the 3 images
	slideduration: 500 //duration of slide up animation to reveal panel
}

var preloadimagesstatus=[];
var preloadimages=[]; //GEO

function simpleGallery(settingarg){
	this.setting=settingarg
	settingarg=null
	var setting=this.setting
	setting.panelheight=(parseInt(setting.navpanelheight)>5)? parseInt(setting.navpanelheight) : parseInt(simpleGallery_navpanel.panel.height)
	setting.fadeduration=parseInt(setting.fadeduration)
	setting.curimage=(setting.persist)? simpleGallery.routines.getCookie("gallery-"+setting.wrapperid) : 0
	setting.curimage=setting.curimage || 0 //account for curimage being null if cookie is empty
	setting.preloadfirst=(!jQuery.Deferred)? false : (typeof setting.preloadfirst!="undefined")? setting.preloadfirst : true //Boolean on whether to preload all images before showing gallery
	setting.ispaused=!setting.autoplay[0] //ispaused reflects current state of gallery, autoplay[0] indicates whether gallery is set to auto play
	setting.currentstep=0 //keep track of # of slides slideshow has gone through
	setting.totalsteps=setting.imagearray.length*setting.autoplay[2] //Total steps limit: # of images x # of user specified cycles
	setting.fglayer=0, setting.bglayer=1 //index of active and background layer (switches after each change of slide)
	setting.oninit=setting.oninit || function(){}
	setting.onslide=setting.onslide || function(){}
	var longestdesc=null; //GEO
	var dfd = (setting.preloadfirst)? jQuery.Deferred() : {resolve:function(){}, done:function(f){f()}} //create real deferred object unless preloadfirst setting is false or browser doesn't support it
	setting.longestdesc="" //get longest description of all slides. If no desciptions defined, variable contains ""
	setting.$loadinggif=(function(){ //preload and ref ajax loading gif
		var loadgif=new Image()
		loadgif.src=simpleGallery_navpanel.loadinggif
		return jQuery(loadgif).css({verticalAlign:'middle'}).wrap('<div style="position:absolute;text-align:center;width:100%;height:100%;z-index:1001" />').parent()
//		return jQuery(loadgif).css({verticalAlign:'middle'}).wrap('<div style="position:absolute;left:700px;z-index:100000" />').parent()
	})()
	for (var i=0; i<setting.imagearray.length; i++){
		if (setting.imagearray[i][3] && setting.imagearray[i][3].length>setting.longestdesc.length)
			setting.longestdesc=setting.imagearray[i][3]
		if (!setting.imagearray[i][0]) // S'il n'y a pas d'image, on purge
			setting.imagearray.splice(i,1);
	}
//	for (var i=0; i<setting.imagearray.length; i++){  //preload slideshow images
//	var loadedimages=0;
	load_image (0);
	function load_image (i) {
		if (i<setting.imagearray.length){
			preloadimages[i]=new Image()
			preloadimages[i].src=setting.imagearray[i][0]
			jQuery(preloadimages[i]).bind('load error', function(){
	//			if (loadedimages==setting.imagearray.length){
	//			if (loadedimages==1){
				setting.$loadinggif.detach() // On enlève le gif d'attente...
				preloadimagesstatus [i] = true;
				load_image (i + 1);
				if (i==0){ // La première
					dfd.resolve() //indicate 1st image have been loaded
				}
			})
		}
	}
	
	var slideshow=this
	jQuery(document).ready(function($){
		var setting=slideshow.setting
		setting.$wrapperdiv=$('#'+setting.wrapperid).css({position:'relative', left:setting.shift[0]+'px', top:setting.shift[1]+'px', visibility:'visible', background:'black', overflow:'hidden', width:setting.dimensions[0], height:setting.dimensions[1]}).empty() //main gallery DIV
		if (setting.$wrapperdiv.length==0){ //if no wrapper DIV found
			alert("Error: DIV with ID \""+setting.wrapperid+"\" not found on page.")
			return
		}
		setting.$gallerylayers=$('<div class="gallerylayer"></div><div class="gallerylayer"></div>') //two stacked DIVs to display the actual slide 
			.css({position:'absolute', left:0, top:0})
			.appendTo(setting.$wrapperdiv)
		setting.$loadinggif.css({top:setting.dimensions[1]/2-37}).appendTo(setting.$wrapperdiv) //37 is assumed height of ajax loading gif
		setting.gallerylayers=setting.$gallerylayers.get() //cache stacked DIVs as DOM objects
		setting.navbuttons=simpleGallery.routines.addnavpanel(setting) //get 4 nav buttons DIVs as DOM objects
		if (setting.longestdesc!="") //if at least one slide contains a description (feature is enabled)
			setting.descdiv=simpleGallery.routines.adddescpanel(setting)
		$(setting.navbuttons).filter('img.navimages').css({opacity:0.8})
			.bind('mouseover mouseout', function(e){
				$(this).css({opacity:(e.type=="mouseover")? 1 : 0.8})
			})
			.bind('click', function(e){
				var keyword=e.target.title.toLowerCase()
				slideshow.navigate(keyword) //assign behavior to nav images
			})
		dfd.done(function(){ //execute when all images have loaded
//			setting.$loadinggif.remove()
//			setting.$loadinggif.detach()
//			setting.$wrapperdiv.bind('mouseenter', function(){slideshow.showhidenavpanel('show')})
			slideshow.showhidenavpanel('show'); //GEO
/*GEO
			setting.$wrapperdiv.bind('mousemove',  function(e){
				slideshow.showhidenavpanel('show');
				if (e.offsetY > 50 && e.offsetY < $(window).height() - 50) // Pour le bas et le haut de la fenetre
					slideshow.hidetimer=setTimeout(function(){mygallery.showhidenavpanel('hide')}, 2000)
			})
			setting.$wrapperdiv.bind('mouseleave', function(){slideshow.showhidenavpanel('hide')})
GEO*/
			$('body').bind('mouseenter', function(){slideshow.showhidenavpanel('show')}) //GEO
			$('body').bind('mouseleave', function(){slideshow.showhidenavpanel('hide')}) //GEO

			slideshow.showslide(setting.curimage) //show initial slide
			setting.oninit.call(slideshow) //trigger oninit() event
			$(window).bind('unload', function(){ //clean up and persist
				$(slideshow.setting.navbuttons).unbind()
				if (slideshow.setting.persist) //remember last shown image's index
					simpleGallery.routines.setCookie("gallery-"+setting.wrapperid, setting.curimage)
				jQuery.each(slideshow.setting, function(k){
					if (slideshow.setting[k] instanceof Array){
						for (var i=0; i<slideshow.setting[k].length; i++){
							if (slideshow.setting[k][i].tagName=="DIV") //catches 2 gallerylayer divs, gallerystatus div
								slideshow.setting[k][i].innerHTML=null
							slideshow.setting[k][i]=null
						}
					}
					if (slideshow.setting[k].innerHTML) //catch gallerydesctext div
						slideshow.setting[k].innerHTML=null
					slideshow.setting[k]=null
				})
				slideshow=slideshow.setting=null
			})
		}) //end deferred code
	}) //end jQuery domload
}

simpleGallery.prototype={

	navigate:function(keyword){
		clearTimeout(this.setting.playtimer)
		this.setting.totalsteps=100000 //if any of the nav buttons are clicked on, set totalsteps limit to an "unreachable" number 
		if (!isNaN(parseInt(keyword))){
			this.showslide(parseInt(keyword))
		}
		else if (/(prev)|(next)/i.test(keyword)){
			this.showslide(keyword.toLowerCase())
		}
		else{ //if play|pause button
			var slideshow=this
			var $playbutton=$(this.setting.navbuttons).eq(1)
			if (!this.setting.ispaused){ //if pause Gallery
				this.setting.autoplay[0]=false
				$playbutton.attr({title:'Play', src:simpleGallery_navpanel.images[1]})
			}
			else if (this.setting.ispaused){ //if play Gallery
				this.setting.autoplay[0]=true
				this.setting.playtimer=setTimeout(function(){slideshow.showslide('next')}, this.setting.autoplay[1])
				$playbutton.attr({title:'Pause', src:simpleGallery_navpanel.images[3]})
			}
			slideshow.setting.ispaused=!slideshow.setting.ispaused
		}
	},

	showslide:function(keyword){
		var slideshow=this
		var setting=slideshow.setting
		var totalimages=setting.imagearray.length
		var imgindex=(keyword=="next")? (setting.curimage<totalimages-1? setting.curimage+1 : 0)
			: (keyword=="prev")? (setting.curimage>0? setting.curimage-1 : totalimages-1)
			: Math.min(keyword, totalimages-1)
		if (!preloadimagesstatus[imgindex]) { // Si l'image n'est pas chargée, on attend un peu 
			this.setting.$loadinggif.appendTo(this.setting.$wrapperdiv) // On remet le gif d'attente
			setting.playtimer=setTimeout(function(){slideshow.showslide(keyword)}, setting.autoplay[1])
			return;
		}
		setting.gallerylayers[setting.bglayer].innerHTML=simpleGallery.routines.getSlideHTML(setting.imagearray[imgindex])
		setting.$gallerylayers.eq(setting.bglayer).css({zIndex:1000, opacity:0, //background layer becomes foreground
            top: Math.round ((u*3 - preloadimages[imgindex].height) / 2), left: Math.round ((u*4 - preloadimages[imgindex].width) / 2)}) //GEO
			.stop().css({opacity:0}).animate({opacity:1}, setting.fadeduration, function(){ //Callback function after fade animation is complete:
				clearTimeout(setting.playtimer)
				setting.gallerylayers[setting.bglayer].innerHTML=null  //empty bglayer (previously fglayer before setting.fglayer=setting.bglayer was set below)
				try{
					setting.onslide.call(slideshow, setting.gallerylayers[setting.fglayer], setting.curimage)
				}catch(e){
					alert("Simple Controls Gallery: An error has occured somwhere in your code attached to the \"onslide\" event: "+e)
				}
				setting.currentstep+=1
				if (setting.autoplay[0]){
					if (setting.currentstep<=setting.totalsteps)
						setting.playtimer=setTimeout(function(){slideshow.showslide('next')}, setting.autoplay[1])
					else
						slideshow.navigate("play/pause")
				}
			}) //end callback function
		setting.gallerylayers[setting.fglayer].style.zIndex=999 //foreground layer becomes background
		setting.fglayer=setting.bglayer
		setting.bglayer=(setting.bglayer==0)? 1 : 0
		setting.curimage=imgindex
		
//GEO DCMM		var indexbar = '<a class="nolinkform" href="'+setting.returnlink+'" title="Voir sous forme de forum">'+setting.title+'</a> ', prevshown = 0; //GEO
		var indexbar = '', prevshown = 0; //GEO
		for (var i=0; i<setting.imagearray.length; i++)	
			if (preloadimagesstatus [i]) {
				var i5 = i%5, dist = Math.abs(i-imgindex);
				if (!i5 || dist < 5) {
					if (i == prevshown + 1)
						indexbar += ' ';
					else if (i)
						indexbar += '..';
					if (setting.imagearray[i].length > 3) {//GEO S'il y a un texte
						var texte = setting.imagearray[i][3].split('<');
						indexbar += '<span onclick="mygallery.navigate('+i+')" class="'+(i==imgindex?'indeximgtrue':'indeximg')+'" title="'+texte[0]+'">'+(i==imgindex?'('+(i+1)+')':i+1)+'</span>';
					}
					prevshown = i;
				}
			}
		if (prevshown != setting.imagearray.length-1)
			indexbar += '..';
		if (preloadimagesstatus.length != setting.imagearray.length)
			indexbar += 'chargement';
		setting.navbuttons[3].innerHTML=indexbar
		if (setting.imagearray[imgindex][3]){ //if this slide contains a description
			setting.$descpanel.css({visibility:'visible'})
			setting.descdiv.innerHTML=setting.imagearray[imgindex][3]
		}
		else if (setting.longestdesc!=""){ //if at least one slide contains a description (feature is enabled)
			setting.descdiv.innerHTML=null
			setting.$descpanel.css({visibility:'hidden'})
		}
	},

	showhidenavpanel:function(state){
		var setting=this.setting
		var endpoint=(state=="show")? setting.dimensions[1]-setting.panelheight : this.setting.dimensions[1]
		setting.$navpanel.stop().animate({top:endpoint}, simpleGallery_navpanel.slideduration)
		if (setting.longestdesc!="") //if at least one slide contains a description (feature is enabled)
			this.showhidedescpanel(state)
		if (this.hidetimer) {
			clearTimeout(this.hidetimer);
			this.hidetimer = null;
		}
	},

	showhidedescpanel:function(state){
		var setting=this.setting
		var endpoint=(state=="show")? 0 : -setting.descpanelheight
		setting.$descpanel.stop().animate({top:endpoint}, simpleGallery_navpanel.slideduration)
	}
}

simpleGallery.routines={

	getSlideHTML:function(imgelement){
		var layerHTML=(imgelement[1])? '<a href="'+imgelement[1]+'" target="'+imgelement[2]+'">\n' : '' //hyperlink slide?
		layerHTML+='<img src="'+imgelement[0]+'" style="border-width:0" />'
		layerHTML+=(imgelement[1])? '</a>' : ''
		return layerHTML //return HTML for this layer
	},

	addnavpanel:function(setting){
		var interfaceHTML=''
		for (var i=0; i<3; i++){
			var imgstyle='position:relative; border:0; cursor:hand; cursor:pointer; top:'+simpleGallery_navpanel.imageSpacing.offsetTop[i]+'px; margin-right:'+(i!=2? simpleGallery_navpanel.imageSpacing.spacing+'px' : 0)
			var title=(i==0? 'Prev' : (i==1)? (setting.ispaused? 'Play' : 'Pause') : 'Next')
			var imagesrc=(i==1)? simpleGallery_navpanel.images[(setting.ispaused)? 1 : 3] : simpleGallery_navpanel.images[i]
			interfaceHTML+='<img class="navimages" title="' + title + '" src="'+ imagesrc +'" style="'+imgstyle+'" /> '
		}
		interfaceHTML+='<div class="gallerystatus" style="margin-top:1px">' + (setting.curimage+1) + '/' + setting.imagearray.length + '</div>'
		setting.$navpanel=$('<div class="navpanellayer"></div>')
			.css({position:'absolute', width:'100%', height:setting.panelheight, left:0, top:setting.dimensions[1], font:simpleGallery_navpanel.panel.fontStyle, zIndex:'1010'})
			.appendTo(setting.$wrapperdiv)
		$('<div class="navpanelbg"></div><div class="navpanelfg"></div>') //create inner nav panel DIVs
			.css({position:'absolute', left:0, top:0, width:'100%', height:'100%'})
			.eq(0).css({background:'black', opacity:simpleGallery_navpanel.panel.opacity}).end() //"navpanelbg" div
			.eq(1).css({paddingTop:simpleGallery_navpanel.panel.paddingTop, textAlign:'center', color:'white'}).html(interfaceHTML).end() //"navpanelfg" div
			.appendTo(setting.$navpanel)
		return setting.$navpanel.find('img.navimages, div.gallerystatus').get() //return 4 nav related images and DIVs as DOM objects
	},

	adddescpanel:function(setting){
		setting.$descpanel=$('<div class="gallerydesc"><div class="gallerydescbg"></div><div class="gallerydescfg"><div class="gallerydesctext"></div></div></div>')
			.css({position:'absolute', width:'100%', left:0, top:-1000, zIndex:'1010'})
			.find('div').css({position:'absolute', left:0, top:0, width:'100%'})
			.eq(0).css({background:'black', opacity:simpleGallery_navpanel.panel.opacity}).end() //"gallerydescbg" div
			.eq(1).css({color:'white'}).end() //"gallerydescfg" div
			.eq(2).html(setting.longestdesc).end().end()
			.appendTo(setting.$wrapperdiv)
		var $gallerydesctext=setting.$descpanel.find('div.gallerydesctext')
		setting.descpanelheight=$gallerydesctext.outerHeight()
		setting.$descpanel.css({top:-setting.descpanelheight, height:setting.descpanelheight}).find('div').css({height:'100%'})
		return setting.$descpanel.find('div.gallerydesctext').get(0) //return gallery description DIV as a DOM object
	},

	getCookie:function(Name){ 
		var re=new RegExp(Name+"=[^;]+", "i"); //construct RE to search for target name/value pair
		if (document.cookie.match(re)) //if cookie found
			return document.cookie.match(re)[0].split("=")[1] //return its value
		return null
	},

	setCookie:function(name, value){
		document.cookie = name+"=" + value + ";path=/"
	}
}