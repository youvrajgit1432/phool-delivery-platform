document.addEventListener('DOMContentLoaded', function(){
  const productId = window.ReviewsConfig && window.ReviewsConfig.productId ? window.ReviewsConfig.productId : null;
  if (!productId) return;

  const fetchReviews = () => {
    fetch('/api/reviews.php?product_id=' + productId)
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        renderReviews(data.reviews || []);
      });
  };

  const showToast = (msg) => {
    let t = document.querySelector('.success-toast');
    if (!t) {
      t = document.createElement('div'); t.className = 'success-toast'; document.body.appendChild(t);
    }
    t.textContent = msg; t.style.display = 'block';
    setTimeout(()=> t.style.display='none', 2500);
  };

  const renderReviews = (reviews) => {
    const container = document.querySelector('.review-list');
    if (!container) return;
    container.innerHTML = '';
    if (reviews.length === 0) {
      container.innerHTML = '<div class="no-reviews small-muted">No reviews yet.</div>';
      return;
    }

    reviews.forEach(r => {
      const div = document.createElement('div'); div.className = 'review-item';
      div.innerHTML = `
        <div class="review-avatar">${(r.customer_name||r.guest_name||'A').charAt(0).toUpperCase()}</div>
        <div class="review-body">
          <div class="review-header"><strong>${escapeHtml(r.customer_name||r.guest_name||'Anonymous')}</strong> <span class="small-muted">• ${escapeHtml((new Date(r.created_at)).toLocaleDateString())}</span></div>
          <div class="review-meta">${renderStars(r.rating)} <span class="small-muted">${r.verified_purchase? 'Verified Purchase' : ''}</span></div>
          <h4>${escapeHtml(r.title)}</h4>
          <div class="review-content">${escapeHtml(r.content).replace(/\n/g,'<br>')}</div>
          <div class="review-actions">
            <button data-id="${r.id}" data-action="like" class="btn-like">👍 ${r.helpful_count||0}</button>
            <button data-id="${r.id}" data-action="dislike" class="btn-dislike">👎 ${r.unhelpful_count||0}</button>
            <button data-id="${r.id}" data-action="comment" class="btn-comment small-muted">Comment</button>
          </div>
          <div class="comments-placeholder"></div>
        </div>`;
      container.appendChild(div);
    });

    // attach events
    container.querySelectorAll('.btn-like, .btn-dislike').forEach(btn => {
      btn.addEventListener('click', function(){
        const id = this.getAttribute('data-id');
        const reaction = this.getAttribute('data-action');
        fetch('/api/reviews.php', {method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'vote', review_id: id, reaction: reaction})})
          .then(r=>r.json()).then(res=>{ if(res.success) { showToast('Thanks for your feedback'); fetchReviews(); } else alert(res.message); });
      });
    });

    container.querySelectorAll('.btn-comment').forEach(btn => {
      btn.addEventListener('click', function(){
        const holder = this.closest('.review-item').querySelector('.comments-placeholder');
        if (holder.innerHTML.trim() === '') {
          holder.innerHTML = `<div class="review-comment"><textarea class="comment-text" placeholder="Write a reply..."></textarea><div style="margin-top:8px;"><button class="submit-comment">Post</button></div></div>`;
          holder.querySelector('.submit-comment').addEventListener('click', function(){
            const text = holder.querySelector('.comment-text').value.trim();
            if(!text) return alert('Comment required');
            const reviewId = btn.getAttribute('data-id');
            fetch('/api/reviews.php', {method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'comment', review_id: reviewId, comment: text})})
              .then(r=>r.json()).then(res=>{ if(res.success){ showToast('Comment submitted'); holder.innerHTML = ''; } else alert(res.message); });
          });
        } else holder.innerHTML = '';
      });
    });
  };

  function renderStars(n){ return '<span style="color:#f6b042">' + '★'.repeat(parseInt(n||0)) + '</span>'; }
  function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>\"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c];}); }

  // write review modal trigger
  const writeBtn = document.querySelector('.write-review-btn');
  if (writeBtn) {
    writeBtn.addEventListener('click', function(){
      const formHtml = `
        <div class="reviews-container">
          <h3>Write a review</h3>
          <div class="review-form">
            <input id="rv-title" placeholder="Summary (eg. Great quality)" />
            <select id="rv-rating"><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Okay</option><option value="2">2 - Poor</option><option value="1">1 - Terrible</option></select>
            <textarea id="rv-content" placeholder="Share your experience"></textarea>
            <div><button id="rv-submit">Submit Review</button> <button id="rv-cancel">Cancel</button></div>
          </div>
        </div>`;
      const modal = document.createElement('div'); modal.className='reviews-modal'; modal.style.position='fixed'; modal.style.left=0; modal.style.top=0; modal.style.right=0; modal.style.bottom=0; modal.style.background='rgba(0,0,0,0.4)'; modal.innerHTML = `<div style="max-width:640px;margin:60px auto;background:#fff;padding:16px;border-radius:8px;">${formHtml}</div>`;
      document.body.appendChild(modal);
      modal.querySelector('#rv-cancel').addEventListener('click', ()=>modal.remove());
      modal.querySelector('#rv-submit').addEventListener('click', ()=>{
        const title = modal.querySelector('#rv-title').value.trim();
        const rating = modal.querySelector('#rv-rating').value;
        const content = modal.querySelector('#rv-content').value.trim();
        if (!content) return alert('Please enter review content');
        fetch('/api/reviews.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'submit', product_id: productId, rating: rating, title: title, content: content})})
          .then(r=>r.json()).then(res=>{ if(res.success){ showToast('Review submitted'); modal.remove(); fetchReviews(); } else alert(res.message); });
      });
    });
  }

  fetchReviews();
});
