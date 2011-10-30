
function hasSupport(){if(typeof hasSupport.support!="undefined")
return hasSupport.support;var ie55=/msie 5\.[56789]/i.test(navigator.userAgent);hasSupport.support=(typeof document.implementation!="undefined"&&document.implementation.hasFeature("html","1.0")||ie55)
if(ie55){document._getElementsByTagName=document.getElementsByTagName;document.getElementsByTagName=function(sTagName){if(sTagName=="*")
return document.all;else
return document._getElementsByTagName(sTagName);};}
return hasSupport.support;}
function WebFXTabPane(el,bUseCookie){if(!hasSupport()||el==null)return;this.element=el;this.element.tabPane=this;this.pages=[];this.selectedIndex=null;this.useCookie=bUseCookie!=null?bUseCookie:true;this.element.className=this.classNameTag+" "+this.element.className;this.tabRow=document.createElement("div");this.tabRow.className="tab-row";el.insertBefore(this.tabRow,el.firstChild);var tabIndex=0;if(this.useCookie){tabIndex=Number(WebFXTabPane.getCookie("webfxtab_"+this.element.id));if(isNaN(tabIndex))
tabIndex=0;}
this.selectedIndex=tabIndex;var cs=el.childNodes;var n;for(var i=0;i<cs.length;i++){if(cs[i].nodeType==1&&cs[i].className=="tab-page"){this.addTabPage(cs[i]);}}}
WebFXTabPane.prototype.classNameTag="dynamic-tab-pane-control";WebFXTabPane.prototype.setSelectedIndex=function(n){if(this.selectedIndex!=n){if(this.selectedIndex!=null&&this.pages[this.selectedIndex]!=null)
this.pages[this.selectedIndex].hide();this.selectedIndex=n;this.pages[this.selectedIndex].show();if(this.useCookie)
WebFXTabPane.setCookie("webfxtab_"+this.element.id,n);}};WebFXTabPane.prototype.getSelectedIndex=function(){return this.selectedIndex;};WebFXTabPane.prototype.addTabPage=function(oElement,callBackFnc){if(!hasSupport())return;if(oElement.tabPage==this)
return oElement.tabPage;var n=this.pages.length;var tp=this.pages[n]=new WebFXTabPage(oElement,this,n,callBackFnc);tp.tabPane=this;this.tabRow.appendChild(tp.tab);if(n==this.selectedIndex)
tp.show();else
tp.hide();return tp;};WebFXTabPane.prototype.dispose=function(){this.element.tabPane=null;this.element=null;this.tabRow=null;for(var i=0;i<this.pages.length;i++){this.pages[i].dispose();this.pages[i]=null;}
this.pages=null;};WebFXTabPane.setCookie=function(sName,sValue,nDays){var expires="";if(nDays){var d=new Date();d.setTime(d.getTime()+nDays*24*60*60*1000);expires="; expires="+d.toGMTString();}
document.cookie=sName+"="+sValue+expires+"; path=/";};WebFXTabPane.getCookie=function(sName){var re=new RegExp("(\;|^)[^;]*("+sName+")\=([^;]*)(;|$)");var res=re.exec(document.cookie);return res!=null?res[3]:null;};WebFXTabPane.removeCookie=function(name){setCookie(name,"",-1);};function WebFXTabPage(el,tabPane,nIndex,callBackFnc){if(!hasSupport()||el==null)return;this.element=el;this.element.tabPage=this;this.callBack=callBackFnc;this.index=nIndex;var cs=el.childNodes;for(var i=0;i<cs.length;i++){if(cs[i].nodeType==1&&cs[i].className=="tab"){this.tab=cs[i];break;}}
var a=document.createElement("SPAN");this.aElement=a;a.href="#";a.onclick=function(){return false;};while(this.tab.hasChildNodes())
a.appendChild(this.tab.firstChild);this.tab.appendChild(a);var oThis=this;this.tab.onclick=function(){return oThis.select();};this.tab.onmouseover=function(){WebFXTabPage.tabOver(oThis);};this.tab.onmouseout=function(){WebFXTabPage.tabOut(oThis);};}
WebFXTabPage.prototype.show=function(){var el=this.tab;var s=el.className+" selected";s=s.replace(/ +/g," ");el.className=s;this.element.style.display="block";};WebFXTabPage.prototype.hide=function(){var el=this.tab;var s=el.className;s=s.replace(/ selected/g,"");el.className=s;this.element.style.display="none";};WebFXTabPage.prototype.select=function(){this.tabPane.setSelectedIndex(this.index);if(this.callBack)this.callBack();return false;};WebFXTabPage.prototype.dispose=function(){this.aElement.onclick=null;this.aElement=null;this.element.tabPage=null;this.tab.onclick=null;this.tab.onmouseover=null;this.tab.onmouseout=null;this.tab=null;this.tabPane=null;this.element=null;};WebFXTabPage.tabOver=function(tabpage){var el=tabpage.tab;var s=el.className+" hover";s=s.replace(/ +/g," ");el.className=s;};WebFXTabPage.tabOut=function(tabpage){var el=tabpage.tab;var s=el.className;s=s.replace(/ hover/g,"");el.className=s;};function setupAllTabs(){if(!hasSupport())return;var all=document.getElementsByTagName("*");var l=all.length;var tabPaneRe=/tab\-pane/;var tabPageRe=/tab\-page/;var cn,el;var parentTabPane;for(var i=0;i<l;i++){el=all[i]
cn=el.className;if(cn=="")continue;if(tabPaneRe.test(cn)&&!el.tabPane)
new WebFXTabPane(el);else if(tabPageRe.test(cn)&&!el.tabPage&&tabPaneRe.test(el.parentNode.className)){el.parentNode.tabPane.addTabPage(el);}}}
function disposeAllTabs(){if(!hasSupport())return;var all=document.getElementsByTagName("*");var l=all.length;var tabPaneRe=/tab\-pane/;var cn,el;var tabPanes=[];for(var i=0;i<l;i++){el=all[i]
cn=el.className;if(cn=="")continue;if(tabPaneRe.test(cn)&&el.tabPane)
tabPanes[tabPanes.length]=el.tabPane;}
for(var i=tabPanes.length-1;i>=0;i--){tabPanes[i].dispose();tabPanes[i]=null;}}
if(typeof window.addEventListener!="undefined")
window.addEventListener("load",setupAllTabs,false);else if(typeof window.attachEvent!="undefined"){window.attachEvent("onload",setupAllTabs);window.attachEvent("onunload",disposeAllTabs);}
else{if(window.onload!=null){var oldOnload=window.onload;window.onload=function(e){oldOnload(e);setupAllTabs();};}
else
window.onload=setupAllTabs;}