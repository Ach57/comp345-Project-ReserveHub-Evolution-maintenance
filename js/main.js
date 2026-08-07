
// English fallback used only on first render before localization.js resolves
const MAIN_FALLBACK = {
    'index.featured.loading':        "Loading Restaurants...",
    'index.featured.noRestaurants':  "No restaurants found in this category.",
    'index.featured.serverError':    "Could not connect to the server.",
    'index.featured.noImage':        "No Image Available",
    'index.featured.reserve':        "Reserve a Table",
    'index.featured.openNow':        "Open now",
};

function t(key) {
    return window.reservehubTranslations?.[key] ?? MAIN_FALLBACK[key] ?? key;
}

// ===== NAVBAR SCROLL =====
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    navbar?.classList.toggle('scrolled', window.scrollY > 40);
});

// ===== HAMBURGER MENU =====
const hamburger = document.getElementById('hamburger');
const mobileNav = document.getElementById('mobileNav');

hamburger?.addEventListener('click', () => {
    const isOpen = mobileNav?.classList.toggle('open');
    hamburger?.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

// Close mobile nav when a link is clicked
mobileNav?.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        mobileNav.classList.remove('open');
        hamburger?.classList.remove('active');
        document.body.style.overflow = '';
    });
});

// ===== DYNAMIC RESTAURANTS (FEATURED SECTION) =====
const featuredGrid = document.querySelector('.restaurants-grid');
const filterTabs = document.querySelectorAll('.filter-tab');

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
            setTimeout(() => entry.target.classList.add('visible'), i * 80);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

const fetchFeatured = (category = 'all') => {
    if (!featuredGrid) return;
    
    // Show loading state
    featuredGrid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--orange)"></i><br><br>${t('index.featured.loading')}</div>`;

    let url = '../api/search.php';
    if (category !== 'all') {
        url += `?q=${encodeURIComponent(category)}`;
    }

    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                renderRestaurants(res.data);
            } else {
                featuredGrid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:var(--text-muted)">${res.message}</div>`;
            }
        })
        .catch(err => {
            featuredGrid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:var(--text-muted)">${t('index.featured.serverError')}</div>`;
        });
};

const renderRestaurants = (data) => {
    featuredGrid.innerHTML = '';
    if (data.length === 0) {
        featuredGrid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color:var(--text-muted)">${t('index.featured.noRestaurants')}</div>`;
        return;
    }

    data.slice(0, 8).forEach(item => {
        const card = document.createElement('div');
        card.className = 'restaurant-card reveal';
        card.innerHTML = `
            <div class="card-image">
                <img src="${item.image_url}" alt="${item.name}" onerror="this.onerror=null; this.src=''; this.parentElement.classList.add('no-image');" style="width:100%; height:100%; object-fit:cover;">
                <div class="no-image-overlay"><i class="fa-solid fa-utensils"></i><span>${t('index.featured.noImage')}</span></div>
                <div class="card-img-overlay">
                    <span class="halal-tag" style="background: ${item.is_halal == 1 ? '#27ae60' : '#e74c3c'}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; position: absolute; top: 10px; left: 10px;">${item.is_halal == 1 ? 'HALAL' : 'NON-HALAL'}</span>
                    <button class="wishlist-btn" data-id="${item.id}" aria-label="Add to wishlist"><i class="fa-regular fa-heart"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="card-top">
                    <h3 class="card-name">${item.name}</h3>
                    <span class="card-price">${item.price_range}</span>
                </div>
                <div class="card-meta">
                    <span><i class="fa-solid fa-star"></i> ${item.rating}</span>
                    <span><i class="fa-solid fa-location-dot"></i> ${item.location}</span>
                    <span><i class="fa-regular fa-clock"></i> ${t('index.featured.openNow')}</span>
                </div>
                <p class="card-desc">${item.description}</p>
                <button class="reserve-btn" onclick="window.location.href='restaurant.html?id=${item.id}'">${t('index.featured.reserve')}</button>
            </div>
        `;
        featuredGrid.appendChild(card);
        observer.observe(card);
    });
};

// Initial load and tab listeners
if (featuredGrid) {
    fetchFeatured();
    filterTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            filterTabs.forEach(el => el.classList.remove('active'));
            tab.classList.add('active');
            fetchFeatured(tab.dataset.filter);
        });
    });
}

// Initial observer for static elements
document.querySelectorAll('.reveal, .step-card, .testimonial-card, .section-header').forEach(el => {
    el.classList.add('reveal');
    observer.observe(el);
});

// ===== WISHLIST TOGGLE =====
document.addEventListener('click', e => {
    const btn = e.target.closest('.wishlist-btn');
    if (!btn) return;

    const userStr = localStorage.getItem('reservehub_user') || sessionStorage.getItem('reservehub_user');
    if (!userStr) {
        window.location.href = 'login-signup.html';
        return;
    }

    const user = JSON.parse(userStr);
    const restId = btn.dataset.id;
    if (!restId) return;

    // Visual feedback immediately
    btn.classList.toggle('liked');
    const icon = btn.querySelector('i');
    const currentlyLiked = btn.classList.contains('liked');
    icon.className = currentlyLiked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
    icon.style.color = currentlyLiked ? '#ff4757' : '';

    fetch('../api/toggle_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ restaurant_id: restId, user_id: user.id })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            // Revert if failed
            btn.classList.toggle('liked');
            icon.className = !currentlyLiked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
            icon.style.color = !currentlyLiked ? '#ff4757' : '';
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
    });
});

// ===== SMOOTH SCROLL FOR NAV LINKS =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Re-render cards when localization.js updates window.reservehubTranslations
window.addEventListener("reservehub:languageChanged", () => {
    fetchFeatured();
});