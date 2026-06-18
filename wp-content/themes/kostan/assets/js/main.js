import Swiper from 'swiper';
import { Autoplay, Pagination, EffectFade, Navigation } from 'swiper/modules';

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
  
  // Sync header spacer and CSS vars for fixed header offsets.
  const header = document.getElementById('masthead');
  const spacer = document.querySelector('.site-header-spacer');
  if (header && spacer) {
    const syncHeaderMetrics = () => {
      const adminBar = document.getElementById('wpadminbar');
      const offsetTop = adminBar ? adminBar.offsetHeight : 0;
      const headerHeight = header.offsetHeight;
      const stackHeight = offsetTop + headerHeight;

      spacer.style.height = `${headerHeight}px`;
      document.documentElement.style.setProperty('--header-height', `${headerHeight}px`);
      document.documentElement.style.setProperty('--header-offset-top', `${offsetTop}px`);
      document.documentElement.style.setProperty('--header-stack-height', `${stackHeight}px`);
    };

    syncHeaderMetrics();
    window.addEventListener('resize', syncHeaderMetrics);
    window.addEventListener('load', syncHeaderMetrics);

    if (typeof ResizeObserver !== 'undefined') {
      const headerObserver = new ResizeObserver(syncHeaderMetrics);
      headerObserver.observe(header);

      const adminBar = document.getElementById('wpadminbar');
      if (adminBar) {
        headerObserver.observe(adminBar);
      }
    }
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

  // Latest News Slider (Swiper)
  document.querySelectorAll('.latest-news__swiper').forEach(el => {
    new Swiper(el, {
      modules: [Autoplay, Pagination],
      slidesPerView: 1.15,
      spaceBetween: 12,
      loop: false,
      autoplay: { delay: 4000, disableOnInteraction: true },
      pagination: {
        el: el.querySelector('.latest-news__pagination'),
        clickable: true,
      },
      breakpoints: {
        750: {
          slidesPerView: 3,
          spaceBetween: 16,
        },
      },
    });
  });

  // Calendar area filter (custom dropdown)
  const filterWrap = document.getElementById('calendar-area-filter');
  if (filterWrap) {
    const toggle = filterWrap.querySelector('.calendar-filter__toggle');
    const label  = filterWrap.querySelector('.calendar-filter__label');
    const options = filterWrap.querySelectorAll('.calendar-filter__option');

    // Toggle open/close
    toggle.addEventListener('click', () => {
      const open = filterWrap.getAttribute('aria-expanded') === 'true';
      filterWrap.setAttribute('aria-expanded', !open);
      toggle.setAttribute('aria-expanded', !open);
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
      if (!filterWrap.contains(e.target)) {
        filterWrap.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Option selection
    options.forEach(opt => {
      opt.addEventListener('click', () => {
        const slug = opt.dataset.value;

        // Update active state
        options.forEach(o => o.classList.remove('calendar-filter__option--active'));
        opt.classList.add('calendar-filter__option--active');

        // Update label
        label.textContent = opt.textContent.trim();

        // Close dropdown
        filterWrap.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-expanded', 'false');

        // Filter entries
        document.querySelectorAll('.calendar-entry').forEach(entry => {
          entry.style.display = (!slug || entry.dataset.area === slug) ? '' : 'none';
        });
      });
    });
  }

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