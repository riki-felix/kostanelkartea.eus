document.addEventListener('DOMContentLoaded', () => {
  // Mobile menu toggle
  const menuToggles = document.querySelectorAll('.menu-toggle'); // ← Get ALL buttons
  const menuPanel = document.querySelector('#menu-panel');
  
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
  
  /**
   * WPML Language Switcher Dropdown
   */
  const langSwitchers = document.querySelectorAll('.js-wpml-ls-legacy-dropdown-click');
  
  langSwitchers.forEach(switcher => {
    const toggle = switcher.querySelector('.js-wpml-ls-item-toggle');
    const submenu = switcher.querySelector('.js-wpml-ls-sub-menu');
    
    if (toggle && submenu) {
      // Toggle dropdown on click
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        
        const isOpen = switcher.classList.contains('wpml-ls-opened');
        
        // Close all other dropdowns first
        document.querySelectorAll('.js-wpml-ls-legacy-dropdown-click').forEach(s => {
          s.classList.remove('wpml-ls-opened');
        });
        
        // Toggle current dropdown
        if (!isOpen) {
          switcher.classList.add('wpml-ls-opened');
          toggle.setAttribute('aria-expanded', 'true');
        } else {
          switcher.classList.remove('wpml-ls-opened');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!switcher.contains(e.target)) {
          switcher.classList.remove('wpml-ls-opened');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
      
      // Close dropdown on Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          switcher.classList.remove('wpml-ls-opened');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
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