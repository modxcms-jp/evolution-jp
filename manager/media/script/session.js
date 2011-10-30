
function keepMeAlive(imgName){myImg=self.mainMenu.document.getElementById(imgName);if(myImg)myImg.src=myImg.src.replace(/\?.*$/,'?'+Math.random());}
window.setInterval("keepMeAlive('keepAliveIMG')",1000*60);