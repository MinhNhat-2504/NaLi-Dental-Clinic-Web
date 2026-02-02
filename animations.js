/**
 * animations.js - Scroll Reveal & Hover Effects
 * NALI Dental Clinic - UI/UX Enhancements
 */

// ============================================
// 1. SCROLL REVEAL ANIMATION
// ============================================

// Intersection Observer để phát hiện khi element vào viewport
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('reveal-active');
            // Unobserve sau khi đã animate để tránh lặp lại
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Khởi tạo Scroll Reveal khi DOM loaded
function initScrollReveal() {
    // Chọn tất cả elements cần reveal
    const revealElements = document.querySelectorAll('.reveal');
    
    revealElements.forEach((element, index) => {
        // Thêm delay tăng dần cho mỗi element
        element.style.transitionDelay = `${index * 0.1}s`;
        observer.observe(element);
    });
}

// ============================================
// 2. STAGGER ANIMATION (Hiệu ứng nối tiếp)
// ============================================

function initStaggerAnimation() {
    const staggerContainers = document.querySelectorAll('.stagger-container');
    
    staggerContainers.forEach(container => {
        const children = container.children;
        
        const staggerObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    Array.from(children).forEach((child, index) => {
                        setTimeout(() => {
                            child.classList.add('stagger-active');
                        }, index * 150); // Delay 150ms cho mỗi item
                    });
                    staggerObserver.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        staggerObserver.observe(container);
    });
}

// ============================================
// 3. COUNTER ANIMATION (Đếm số)
// ============================================

function animateCounter(element, target, duration = 2000) {
    let start = 0;
    const increment = target / (duration / 16);
    
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = Math.floor(target);
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start);
        }
    }, 16);
}

function initCounters() {
    const counters = document.querySelectorAll('.counter');
    
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.getAttribute('data-target'));
                animateCounter(entry.target, target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => counterObserver.observe(counter));
}

// ============================================
// 4. PARALLAX EFFECT (Hiệu ứng thị sai)
// ============================================

function initParallax() {
    const parallaxElements = document.querySelectorAll('.parallax');
    
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        
        parallaxElements.forEach(element => {
            const speed = element.getAttribute('data-speed') || 0.5;
            const yPos = -(scrolled * speed);
            element.style.transform = `translateY(${yPos}px)`;
        });
    });
}

// ============================================
// 5. HOVER TILT EFFECT (3D Tilt khi hover)
// ============================================

function initTiltEffect() {
    const tiltElements = document.querySelectorAll('.tilt-effect');
    
    tiltElements.forEach(element => {
        element.addEventListener('mousemove', (e) => {
            const rect = element.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
        });
        
        element.addEventListener('mouseleave', () => {
            element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
        });
    });
}

// ============================================
// 6. FADE IN UP ANIMATION
// ============================================

function initFadeInUp() {
    const fadeElements = document.querySelectorAll('.fade-in-up');
    
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-active');
                fadeObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    fadeElements.forEach(element => fadeObserver.observe(element));
}

// ============================================
// 7. SMOOTH SCROLL
// ============================================

function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            e.preventDefault();
            const target = document.querySelector(href);
            
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ============================================
// 8. FLOATING ANIMATION (Hiệu ứng lơ lửng)
// ============================================

function initFloatingAnimation() {
    const floatingElements = document.querySelectorAll('.floating');
    
    floatingElements.forEach((element, index) => {
        // Thêm delay khác nhau cho mỗi element
        element.style.animationDelay = `${index * 0.5}s`;
    });
}

// ============================================
// 9. TYPING EFFECT (Hiệu ứng đánh máy)
// ============================================

function typeWriter(element, text, speed = 100) {
    let i = 0;
    element.textContent = '';
    
    function type() {
        if (i < text.length) {
            element.textContent += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }
    
    type();
}

function initTypingEffect() {
    const typingElements = document.querySelectorAll('.typing-effect');
    
    const typingObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const text = entry.target.getAttribute('data-text') || entry.target.textContent;
                const speed = parseInt(entry.target.getAttribute('data-speed')) || 100;
                typeWriter(entry.target, text, speed);
                typingObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    typingElements.forEach(element => typingObserver.observe(element));
}

// ============================================
// 10. PULSE ANIMATION (Hiệu ứng nhấp nháy)
// ============================================

function initPulseAnimation() {
    const pulseElements = document.querySelectorAll('.pulse');
    
    pulseElements.forEach(element => {
        element.addEventListener('mouseenter', () => {
            element.style.animation = 'pulse 0.5s ease-in-out';
        });
        
        element.addEventListener('animationend', () => {
            element.style.animation = '';
        });
    });
}

// ============================================
// KHỞI TẠO TẤT CẢ ANIMATIONS
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Core animations
    initScrollReveal();
    initStaggerAnimation();
    initFadeInUp();
    
    // Interactive effects
    initTiltEffect();
    initSmoothScroll();
    initFloatingAnimation();
    
    // Advanced effects
    initCounters();
    initTypingEffect();
    initPulseAnimation();
    initParallax();
    
    console.log('✨ Animations initialized!');
});

// ============================================
// EXPORT FUNCTIONS (Nếu cần dùng riêng lẻ)
// ============================================

window.NALIAnimations = {
    reveal: initScrollReveal,
    stagger: initStaggerAnimation,
    counter: animateCounter,
    tilt: initTiltEffect,
    typeWriter: typeWriter
};
