import Swiper from 'swiper';
import { Autoplay, Pagination, EffectFade } from 'swiper/modules';

document.addEventListener('DOMContentLoaded', () => {
  // Mobile menu toggle
  const menuToggles = document.querySelectorAll('.menu-toggle'); // ← Get ALL buttons
  const menuPanel = document.querySelector('#menu-panel');
  
  const menuOverlay = document.querySelector('#menu-overlay');

  const closeMenu = () => {
    menuToggles.forEach(btn => btn.setAttribute('aria-expanded', false));
    menuPanel.classList.remove('toggled');
    document.body.classList.remove('menu-open');
  };

  if (menuToggles.length && menuPanel) {
    menuToggles.forEach(button => {
      button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        
        // Update ARIA on ALL toggle buttons
        menuToggles.forEach(btn => {
          btn.setAttribute('aria-expanded', !expanded);
        });
        
        // Toggle menu
        menuPanel.classList.toggle('toggled');
        document.body.classList.toggle('menu-open');
      });
    });
  }

  // Close menu on overlay click
  if (menuOverlay) {
    menuOverlay.addEventListener('click', closeMenu);
  }
  
  // Sync header spacer height with actual header
  const header = document.getElementById('masthead');
  const spacer = document.querySelector('.site-header-spacer');
  if (header && spacer) {
    const sync = () => { spacer.style.height = header.offsetHeight + 'px'; };
    sync();
    window.addEventListener('resize', sync);
  }

  // Hero Carousel (Swiper)
  document.querySelectorAll('.hero-carousel__swiper').forEach(el => {
    new Swiper(el, {
      modules: [Autoplay, Pagination, EffectFade],
      effect: 'fade',
      fadeEffect: { crossFade: true },
      loop: true,
      speed: 800,
      autoplay: { delay: 5000, disableOnInteraction: false },
      pagination: {
        el: el.querySelector('.hero-carousel__pagination'),
        clickable: true,
      },
    });
  });

});
  // // Navigation observer
  // const mainNavigation = document.querySelector('.main-navigation');
  // const homeHero = document.querySelector('.site-header');
  // 
  // if (mainNavigation && homeHero) {
  //   const observer = new IntersectionObserver(
  //     ([entry]) => {
  //       if (entry.isIntersecting) {
  //         mainNavigation.classList.remove('visible');
  //       } else {
  //         mainNavigation.classList.add('visible');
  //       }
  //     },
  //     {
  //       root: null,
  //       threshold: 0,
  //     }
  //   );
  //   
  //   observer.observe(homeHero);
  // }