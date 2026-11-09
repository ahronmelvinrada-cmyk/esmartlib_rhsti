(function(){
  var widget=document.getElementById('chat-widget');
  if(!widget)return;
  var panel=widget.querySelector('#chat-panel');
  var list=widget.querySelector('[data-chat-list]');
  var directory=widget.querySelector('[data-chat-directory]');
  var searchInput=widget.querySelector('[data-chat-search]');
  var results=widget.querySelector('[data-chat-results]');
  var messages=widget.querySelector('[data-chat-messages]');
  var title=widget.querySelector('[data-chat-title]');
  var form=widget.querySelector('[data-chat-form]');
  var input=form.querySelector('input');
  var sendButton=form.querySelector('button');
  var errorBox=widget.querySelector('[data-chat-error]');
  var unreadDot=widget.querySelector('[data-chat-unread]');
  var me=Number(widget.dataset.userId);
  var token=widget.dataset.csrf;
  var active=null,afterId=0,timer=null,busy=false;
  var searchTimer=null,searchRequest=0;

  function request(action,data){
    var body=new URLSearchParams(Object.assign({action:action,csrf_token:token},data||{}));
    return fetch('messages.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
      .then(function(response){
        return response.text().then(function(text){
          var result;
          try{result=JSON.parse(text);}catch(e){
            if(response.url&&/login\.php/i.test(response.url))throw new Error('Your session expired. Sign in again, then refresh this page.');
            throw new Error('Chat server returned an invalid response. Check that the latest messages.php is uploaded.');
          }
          if(!response.ok)throw new Error(result.error||'Chat request failed.');
          return result;
        });
      });
  }
  function showError(text){errorBox.textContent=text||'';}
  function escapeText(text){return String(text||'');}
  function node(tag,className,text){
    var el=document.createElement(tag);
    if(className)el.className=className;
    if(text!==undefined)el.textContent=escapeText(text);
    return el;
  }
  function drawMessages(rows){
    rows.forEach(function(message){
      var bubble=node('div','chat-bubble'+(message.sender_id===me?' mine':''));
      bubble.appendChild(node('p','',message.message));
      bubble.appendChild(node('time','',message.created_at));
      messages.appendChild(bubble);
      afterId=Math.max(afterId,Number(message.id));
    });
    if(rows.length)messages.scrollTop=messages.scrollHeight;
  }
  function refreshConversations(){
    return request('conversations').then(function(result){
      list.replaceChildren();
      var unread=0;
      result.conversations.forEach(function(conversation){
        unread+=conversation.unread;
        var button=node('button','chat-person'+(active&&active.id===conversation.id?' selected':''));
        button.type='button';
        button.appendChild(node('strong','',conversation.name));
        button.appendChild(node('small','',conversation.last_message||conversation.role));
        if(conversation.unread)button.appendChild(node('i','chat-person-dot'));
        button.addEventListener('click',function(){openConversation(conversation);});
        list.appendChild(button);
      });
      unreadDot.hidden=unread===0;
    }).catch(function(e){showError(e.message);});
  }
  function openConversation(conversation){
    active=conversation;
    afterId=0;
    messages.replaceChildren();
    title.textContent=conversation.name+' · '+conversation.role;
    input.disabled=false;
    sendButton.disabled=false;
    directory.hidden=true;
    showError('');
    refreshConversations();
    sync();
  }
  function sync(){
    if(!active||busy||panel.hidden)return;
    busy=true;
    request('sync',{conversation_id:active.id,after_id:afterId}).then(function(result){
      drawMessages(result.messages);
      unreadDot.hidden=result.unread===0;
      return refreshConversations();
    }).catch(function(e){showError(e.message);}).finally(function(){busy=false;});
  }
  function showContacts(){
    directory.hidden=false;
    directory.style.display='';
    searchInput.value='';
    searchInput.focus();
    searchContacts('');
  }
  function searchContacts(search){
    var requestId=++searchRequest;
    results.replaceChildren(node('p','chat-muted','Searching...'));
    request('contacts',{search:search}).then(function(result){
      if(requestId!==searchRequest)return;
      results.replaceChildren();
      result.contacts.forEach(function(person){
        var button=node('button','chat-person',person.name+' · '+person.role);
        button.type='button';
        button.addEventListener('click',function(){
          request('start',{user_id:person.user_id||'',student_id:person.student_id||''}).then(function(result){
            openConversation({id:result.conversation_id,user_id:person.user_id,name:person.name,role:person.role});
            refreshConversations();
          }).catch(function(e){showError(e.message);});
        });
        results.appendChild(button);
      });
      if(!result.contacts.length)results.appendChild(node('p','chat-muted',search?'No matching registered accounts found.':'No other registered accounts are available yet.'));
    }).catch(function(e){if(requestId===searchRequest){showError(e.message);results.replaceChildren();}});
  }
  function openPanel(){
    refreshConversations();
    if(timer)window.clearInterval(timer);
    timer=window.setInterval(function(){refreshConversations();sync();},3000);
  }
  function closePanel(){
    if(timer){window.clearInterval(timer);timer=null;}
  }
  widget.querySelector('[data-chat-open]').addEventListener('chat:toggle',function(event){
    if(event.detail&&event.detail.open)openPanel();
    else closePanel();
  });
  widget.querySelector('[data-chat-new]').addEventListener('chat:new',showContacts);
  searchInput.addEventListener('input',function(){
    if(searchTimer)window.clearTimeout(searchTimer);
    searchTimer=window.setTimeout(function(){searchContacts(searchInput.value.trim());},250);
  });
  form.onsubmit=function(event){
    event.preventDefault();
    var text=input.value.trim();
    if(!active||!text)return;
    sendButton.disabled=true;
    request('send',{conversation_id:active.id,message:text}).then(function(){
      input.value='';
      sync();
    }).catch(function(e){showError(e.message);}).finally(function(){sendButton.disabled=false;input.focus();});
  };
})();
