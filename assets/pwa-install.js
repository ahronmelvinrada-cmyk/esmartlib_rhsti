(function(){
  var promptEvent=null;
  var areas=Array.from(document.querySelectorAll('.pwa-install-area'));
  if(!areas.length)return;

  var standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  if(standalone){
    areas.forEach(function(area){area.hidden=true;});
    return;
  }

  window.addEventListener('beforeinstallprompt',function(event){
    event.preventDefault();
    promptEvent=event;
  });

  window.addEventListener('appinstalled',function(){
    promptEvent=null;
    areas.forEach(function(area){area.hidden=true;});
  });

  function showInstructions(dialog){
    var copy=dialog.querySelector('[data-pwa-help-copy]');
    var steps=dialog.querySelector('[data-pwa-help-steps]');
    var note=dialog.querySelector('[data-pwa-help-note]');
    var ua=navigator.userAgent||'';
    var isAppleMobile=/iPad|iPhone|iPod/.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
    if(!window.isSecureContext){
      copy.textContent='Open the website over HTTPS before installing the app.';
      steps.replaceChildren();
      note.textContent='Make sure the site address starts with https:// and has a valid security certificate.';
    }else if(isAppleMobile){
      copy.textContent='To add this app to your iPhone or iPad, open the website in Safari and follow these steps:';
      steps.replaceChildren();
      ['Tap the Share button.','Select Add to Home Screen.','Tap Add.'].forEach(function(text){
        var item=document.createElement('li');
        item.textContent=text;
        steps.appendChild(item);
      });
      note.textContent='Chrome does not show the install prompt on iPhone or iPad. Use Safari to add the app to your Home Screen.';
    }else{
      copy.textContent='The automatic install prompt did not appear. Try the browser menu:';
      steps.replaceChildren();
      ['Open the Chrome or browser menu.','Select Install app or Add to Home screen.','Confirm the installation.'].forEach(function(text){
        var item=document.createElement('li');
        item.textContent=text;
        steps.appendChild(item);
      });
      note.textContent='If this option is unavailable, open the site in Chrome and make sure the address uses HTTPS.';
    }

    if(typeof dialog.showModal==='function')dialog.showModal();
    else dialog.setAttribute('open','');
  }

  areas.forEach(function(area){
    var button=area.querySelector('[data-pwa-install]');
    var dialog=area.querySelector('[data-pwa-help]');
    var close=area.querySelector('[data-pwa-close]');

    button.addEventListener('click',async function(){
      if(!window.isSecureContext){
        showInstructions(dialog);
        return;
      }
      if(!promptEvent){
        showInstructions(dialog);
        return;
      }

      var installPrompt=promptEvent;
      promptEvent=null;
      try{
        await installPrompt.prompt();
        var result=await installPrompt.userChoice;
        if(result.outcome==='accepted')area.hidden=true;
      }catch(error){
        console.error('App installation prompt failed:',error);
        showInstructions(dialog);
      }
    });

    close.addEventListener('click',function(){
      if(typeof dialog.close==='function')dialog.close();
      else dialog.removeAttribute('open');
    });

    dialog.addEventListener('click',function(event){
      if(event.target===dialog&&typeof dialog.close==='function')dialog.close();
    });
  });
})();
