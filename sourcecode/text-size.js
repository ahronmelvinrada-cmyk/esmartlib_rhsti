(function(){
  const root=document.documentElement;
  const appearanceStorageKey='esl-appearance-v1';
  const textSizes=['small','normal','large','x-large'];
  try{
    const saved=JSON.parse(localStorage.getItem(appearanceStorageKey)||'{}');
    if(textSizes.includes(saved.textSize))root.dataset.textSize=saved.textSize;
  }catch(error){
    console.error('Could not load the text-size preference:',error);
  }

  function applyGlobalTextSize(nodes){
    root.style.setProperty('--text-scale','1','important');
    const selector='body *:not(svg):not(canvas):not(script):not(style):not(template)';
    let elements;
    if(nodes){
      elements=[];
      nodes.forEach(node=>{
        if(node.matches(selector))elements.push(node);
        node.querySelectorAll(selector).forEach(child=>elements.push(child));
      });
    }else{
      elements=document.querySelectorAll(selector);
    }
    elements.forEach(element=>{
      if(element.closest('svg,canvas,script,style,template'))return;
      const baseSize=window.getComputedStyle(element).fontSize;
      element.style.setProperty('--appearance-base-font-size',baseSize);
      element.style.setProperty('font-size','calc(var(--appearance-base-font-size) * var(--text-scale, 1))','important');
    });
    root.style.removeProperty('--text-scale');
  }

  window.applyGlobalTextSize=applyGlobalTextSize;
  applyGlobalTextSize();
  new MutationObserver(records=>{
    const added=[];
    records.forEach(record=>record.addedNodes.forEach(node=>{
      if(node.nodeType===1)added.push(node);
    }));
    if(added.length)applyGlobalTextSize(added);
  }).observe(document.body,{childList:true,subtree:true});
})();
