// Main JavaScript file
console.log('E-Commerce System loaded');

// Add to cart animation
document.querySelectorAll('.product-card form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('button');
        btn.textContent = 'Đã thêm!';
        btn.style.background = '#27ae60';
        
        setTimeout(() => {
            btn.textContent = 'Thêm vào giỏ';
            btn.style.background = '';
        }, 1000);
    });
});
