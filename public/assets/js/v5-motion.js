(() => {
  const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(reduce)return;
  // Subtle perspective tilt. Cards remain fully usable when pointer motion is unavailable.
  const cards=[...document.querySelectorAll('.lg4-listing-card, .lg4-category-card, .lg4-discovery-card')];
  cards.forEach(card=>{
    card.addEventListener('pointermove',event=>{
      if(event.pointerType!=='mouse')return;
      const r=card.getBoundingClientRect();const x=(event.clientX-r.left)/r.width-.5;const y=(event.clientY-r.top)/r.height-.5;
      card.style.setProperty('--lg5-tilt-x',`${(-y*2.2).toFixed(2)}deg`);card.style.setProperty('--lg5-tilt-y',`${(x*2.8).toFixed(2)}deg`);
    });
    card.addEventListener('pointerleave',()=>{card.style.setProperty('--lg5-tilt-x','0deg');card.style.setProperty('--lg5-tilt-y','0deg');});
  });
  // Drag the 3D-inspired globe illustration to rotate its viewpoint.
  const stage=document.querySelector('.lg5-globe-stage');const globe=stage?.querySelector('.lg5-globe');
  if(stage&&globe){let active=false,startX=0,startY=0,rotX=-17,rotY=0;stage.addEventListener('pointerdown',e=>{active=true;startX=e.clientX;startY=e.clientY;stage.setPointerCapture?.(e.pointerId);});stage.addEventListener('pointermove',e=>{if(!active)return;rotY+=(e.clientX-startX)*.45;rotX+=(e.clientY-startY)*.25;startX=e.clientX;startY=e.clientY;globe.style.transform=`rotateX(${rotX}deg) rotateY(${rotY}deg) rotateZ(-17deg)`;});const stop=()=>{active=false;};stage.addEventListener('pointerup',stop);stage.addEventListener('pointercancel',stop);}
  // Parallax is limited to decorative layers and disabled on narrow screens.
  if(window.matchMedia('(min-width: 900px)').matches){
    const layers=[...document.querySelectorAll('.lg4-hero-orb,.lg5-globe-stage')];let ticking=false;
    window.addEventListener('scroll',()=>{if(ticking)return;ticking=true;requestAnimationFrame(()=>{const y=window.scrollY;layers.forEach((el,i)=>el.style.translate=`0 ${Math.min(18,y*(i%2?-.018:.012))}px`);ticking=false;});},{passive:true});
  }
})();
