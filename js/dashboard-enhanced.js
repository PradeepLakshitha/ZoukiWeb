/**
 * Enhanced Dashboard JavaScript
 * 
 * This script adds advanced animations and interactivity to the Zouki dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
  // Initialize loading animation
  const pageBody = document.querySelector('body');
  
  // Create loading overlay
  const loadingOverlay = document.createElement('div');
  loadingOverlay.className = 'loading-overlay';
  loadingOverlay.innerHTML = '<span class="loader"></span>';
  pageBody.appendChild(loadingOverlay);
  
  // Apply staggered animations to dashboard elements
  applyStaggeredAnimations();
  
  // Initialize enhanced counters
  initEnhancedCounters();
  
  // Initialize chart animations
  initChartAnimations();
  
  // Add hover animations for buttons and cards
  addHoverEffects();
  
  // Add card animation observers
  observeCards();
  
  // Hide loading overlay when page is fully loaded
  window.addEventListener('load', function() {
    setTimeout(function() {
      loadingOverlay.classList.add('hidden');
      setTimeout(function() {
        loadingOverlay.remove();
      }, 500);
    }, 500);
  });
});

/**
 * Apply staggered animations to dashboard elements
 */
function applyStaggeredAnimations() {
  // Add staggered animation classes to stats cards
  const statsCards = document.querySelectorAll('.app-card .stats-card');
  statsCards.forEach((card, index) => {
    card.classList.add('fade-in', 'stagger-item');
    card.style.opacity = 0;
  });
  
  // Add slide-up animation to chart cards
  const chartCards = document.querySelectorAll('.chart-container');
  chartCards.forEach(card => {
    card.classList.add('slide-up');
    card.style.opacity = 0;
  });
  
  // Add slide-in-right animation to recent items
  const recentItems = document.querySelectorAll('.recent-item');
  recentItems.forEach((item, index) => {
    item.classList.add('slide-in-right', 'stagger-item');
    item.style.opacity = 0;
  });
  
  // Add float animation to action buttons
  const actionButtons = document.querySelectorAll('.btn-lg');
  actionButtons.forEach(btn => {
    btn.classList.add('fade-in');
    btn.style.opacity = 0;
  });
  
  // Trigger animations after a delay
  setTimeout(() => {
    document.querySelectorAll('.fade-in, .slide-up, .slide-in-right').forEach(el => {
      el.style.opacity = 1;
    });
  }, 300);
}

/**
 * Initialize enhanced animated counters
 */
function initEnhancedCounters() {
  const countElements = document.querySelectorAll('.count-up');
  
  countElements.forEach(el => {
    const target = parseInt(el.getAttribute('data-count')) || 0;
    const prefix = el.getAttribute('data-prefix') || '';
    const suffix = el.getAttribute('data-suffix') || '';
    const duration = parseInt(el.getAttribute('data-duration')) || 1500; // animation duration in ms
    const isPercentage = suffix === '%' || el.classList.contains('percentage');
    const decimalPlaces = isPercentage ? 1 : 0;
    
    const frameDuration = 1000/60; // 60fps
    const totalFrames = Math.round(duration / frameDuration);
    
    // Use different easing functions for different effects
    const easeOutExpo = t => t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
    const easeOutQuart = t => 1 - Math.pow(1 - t, 4);
    const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
    
    // Choose easing based on value type
    const easing = isPercentage ? easeOutCubic : easeOutExpo;
    
    let frame = 0;
    let currentValue = 0;
    
    const counter = setInterval(() => {
      frame++;
      const progress = easing(frame / totalFrames);
      currentValue = isPercentage ? 
        (target * progress).toFixed(decimalPlaces) : 
        Math.round(target * progress);
      
      if (frame === totalFrames) {
        clearInterval(counter);
        el.textContent = `${prefix}${target.toLocaleString()}${suffix}`;
      } else {
        el.textContent = `${prefix}${currentValue.toLocaleString()}${suffix}`;
      }
    }, frameDuration);
    
    // Add emphasizing animation when counter completes
    setTimeout(() => {
      el.classList.add('pulse');
      setTimeout(() => {
        el.classList.remove('pulse');
      }, 1000);
    }, duration);
  });
}

/**
 * Initialize chart animations and enhancements
 */
function initChartAnimations() {
  // If Chart.js is available, extend its animation capabilities
  if (typeof Chart !== 'undefined') {
    // Override default chart animations
    Chart.defaults.animation.duration = 1500;
    Chart.defaults.animation.easing = 'easeOutQuart';
    
    // Add highlighting effect on chart hover
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
      container.addEventListener('mouseenter', () => {
        container.style.transform = 'scale(1.01)';
      });
      
      container.addEventListener('mouseleave', () => {
        container.style.transform = 'scale(1)';
      });
    });
  }
}

/**
 * Add hover effects to interactive elements
 */
function addHoverEffects() {
  // Add hover effects to buttons
  const buttons = document.querySelectorAll('.btn-primary, .btn-outline-primary');
  buttons.forEach(btn => {
    btn.addEventListener('mouseenter', () => {
      btn.style.transform = 'translateY(-2px)';
      btn.style.boxShadow = '0 4px 8px rgba(76, 175, 80, 0.3)';
    });
    
    btn.addEventListener('mouseleave', () => {
      btn.style.transform = '';
      btn.style.boxShadow = '';
    });
  });
  
  // Add hover effects to stats cards
  const statsCards = document.querySelectorAll('.stats-card');
  statsCards.forEach(card => {
    card.addEventListener('mouseenter', () => {
      const icon = card.querySelector('.stats-icon');
      const value = card.querySelector('.stats-value');
      
      if (icon) icon.style.transform = 'scale(1.1) rotate(10deg)';
      if (value) value.style.color = 'var(--primary-color)';
    });
    
    card.addEventListener('mouseleave', () => {
      const icon = card.querySelector('.stats-icon');
      const value = card.querySelector('.stats-value');
      
      if (icon) icon.style.transform = '';
      if (value) value.style.color = '';
    });
  });
  
  // Add hover effects to recent items
  const recentItems = document.querySelectorAll('.recent-item');
  recentItems.forEach(item => {
    item.addEventListener('mouseenter', () => {
      item.style.backgroundColor = 'rgba(76, 175, 80, 0.05)';
      item.style.transform = 'translateX(5px)';
      
      const icon = item.querySelector('.recent-item-icon');
      if (icon) icon.style.transform = 'scale(1.1) rotate(5deg)';
    });
    
    item.addEventListener('mouseleave', () => {
      item.style.backgroundColor = '';
      item.style.transform = '';
      
      const icon = item.querySelector('.recent-item-icon');
      if (icon) icon.style.transform = '';
    });
  });
}

/**
 * Observe cards to animate them when they enter the viewport
 */
function observeCards() {
  // Use Intersection Observer to trigger animations when elements come into view
  if ('IntersectionObserver' in window) {
    const options = {
      root: null,
      rootMargin: '0px',
      threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const card = entry.target;
          
          // Animate card entry
          card.style.opacity = 1;
          card.style.transform = 'translateY(0)';
          
          // Different animations for different card types
          if (card.classList.contains('app-card')) {
            // For regular cards
            card.style.animation = 'fadeIn 0.5s ease-out forwards';
          } else if (card.querySelector('.progress')) {
            // For progress bars
            const progressBars = card.querySelectorAll('.progress-bar');
            progressBars.forEach((bar, index) => {
              setTimeout(() => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                  bar.style.width = width;
                }, 50);
              }, index * 100);
            });
          }
          
          // Stop observing after animation
          observer.unobserve(card);
        }
      });
    }, options);
    
    // Observe all cards
    document.querySelectorAll('.app-card').forEach(card => {
      card.style.opacity = 0;
      card.style.transform = 'translateY(20px)';
      observer.observe(card);
    });
  }
}

/**
 * Enhanced page transitions
 */
function enhancePageTransitions() {
  // Add smooth transitions between pages
  document.querySelectorAll('a').forEach(link => {
    // Only add for internal links
    if (link.hostname === window.location.hostname) {
      link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        
        // Skip for anchor links, javascript links, etc.
        if (href.startsWith('#') || href.startsWith('javascript:') || this.target === '_blank') {
          return;
        }
        
        e.preventDefault();
        
        // Create page transition effect
        const transition = document.createElement('div');
        transition.style.position = 'fixed';
        transition.style.top = '0';
        transition.style.left = '0';
        transition.style.width = '100%';
        transition.style.height = '100%';
        transition.style.backgroundColor = 'rgba(255,255,255,0.8)';
        transition.style.zIndex = '9999';
        transition.style.opacity = '0';
        transition.style.transition = 'opacity 0.3s ease';
        
        document.body.appendChild(transition);
        
        // Fade in
        setTimeout(() => {
          transition.style.opacity = '1';
          
          // Navigate to new page after transition
          setTimeout(() => {
            window.location = href;
          }, 300);
        }, 10);
      });
    }
  });
}

/**
 * Initialize theme toggle functionality
 */
function initThemeToggle() {
  const themeToggle = document.createElement('button');
  themeToggle.className = 'btn btn-sm btn-outline-secondary theme-toggle';
  themeToggle.innerHTML = '<i class="bi bi-moon"></i>';
  themeToggle.style.position = 'fixed';
  themeToggle.style.bottom = '20px';
  themeToggle.style.left = '20px';
  themeToggle.style.zIndex = '999';
  themeToggle.style.borderRadius = '50%';
  themeToggle.style.width = '40px';
  themeToggle.style.height = '40px';
  themeToggle.style.display = 'flex';
  themeToggle.style.alignItems = 'center';
  themeToggle.style.justifyContent = 'center';
  
  document.body.appendChild(themeToggle);
  
  // Check for saved theme preference
  const isDarkMode = localStorage.getItem('darkMode') === 'true';
  
  // Apply theme
  if (isDarkMode) {
    document.body.classList.add('dark-mode');
    themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
  }
  
  // Toggle theme
  themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    
    themeToggle.innerHTML = isDark ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon"></i>';
    localStorage.setItem('darkMode', isDark);
  });
}