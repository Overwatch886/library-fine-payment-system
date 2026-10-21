/*
 * Catalogue page: load categories, search books, render results, borrow.
 */

const API = 'api';

const searchForm = document.getElementById('search-form');
const searchBox = document.getElementById('book-search-box');
const categoryFilter = document.getElementById('category-filter');
const resultsContainer = document.getElementById('book-results');

/* ---------- modal elements ---------- */

const modal = document.getElementById('borrow-modal');
const modalBookTitle = document.getElementById('modal-book-title');
const modalBorrowDate = document.getElementById('modal-borrow-date');
const modalReturnDate = document.getElementById('modal-return-date');
const modalConfirm = document.getElementById('modal-confirm');
const modalCancel = document.getElementById('modal-cancel');

let pendingBookId = null;

/* ---------- helpers ---------- */

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showPlaceholder(msg) {
    resultsContainer.innerHTML = `<p class="results-placeholder">${escapeHtml(msg)}</p>`;
}

function formatDate(date) {
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* ---------- categories ---------- */

async function loadCategories() {
    try {
        const res = await fetch(`${API}/books/categories.php`);
        const json = await res.json();
        if (!json.success) return;

        json.data.forEach((cat) => {
            const value = typeof cat === 'string' ? cat : cat.name;
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            categoryFilter.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}

/* ---------- search ---------- */

async function searchBooks() {
    const q = searchBox.value.trim();
    const category = categoryFilter.value;

    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (category) params.set('category', category);

    try {
        const res = await fetch(`${API}/books/search.php?${params.toString()}`);
        const json = await res.json();

        if (!json.success) {
            showPlaceholder(json.message || 'Search failed.');
            return;
        }

        renderResults(json.data);
    } catch (err) {
        console.error('Search error:', err);
        showPlaceholder('Something went wrong. Please try again.');
    }
}

/* ---------- render ---------- */

function renderResults(books) {
    if (!books || books.length === 0) {
        showPlaceholder('No books found. Try a different search.');
        return;
    }

    resultsContainer.innerHTML = books.map((book) => {
        const available = book.available_copies > 0;
        return `
            <div class="book-card">
                <h3>${escapeHtml(book.title)}</h3>
                <p class="book-author">${escapeHtml(book.author)}</p>
                <p class="book-category">${escapeHtml(book.category)}</p>
                <p class="book-copies">Available: ${book.available_copies} / ${book.total_copies}</p>
                ${available
                    ? `<button class="buttons borrow-btn" data-book-id="${book.book_id}">Borrow</button>`
                    : '<span class="book-unavailable">All copies borrowed</span>'}
            </div>`;
    }).join('');

    resultsContainer.querySelectorAll('.borrow-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const bookId = Number(btn.dataset.bookId);
            const bookTitle = btn.closest('.book-card').querySelector('h3').textContent;
            openBorrowModal(bookId, bookTitle);
        });
    });
}

/* ---------- borrow modal ---------- */

function openBorrowModal(bookId, bookTitle) {
    pendingBookId = bookId;
    modalBookTitle.textContent = bookTitle;

    const today = new Date();
    const returnDate = new Date(today);
    returnDate.setDate(returnDate.getDate() + 14);

    modalBorrowDate.textContent = formatDate(today);
    modalReturnDate.textContent = formatDate(returnDate);

    modal.style.display = 'flex';
}

function closeBorrowModal() {
    modal.style.display = 'none';
    pendingBookId = null;
}

async function confirmBorrow() {
    if (pendingBookId === null) return;

    try {
        const res = await fetch(`${API}/loans/borrow.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ book_id: pendingBookId }),
        });
        const json = await res.json();

        closeBorrowModal();
        alert(json.message);

        if (json.success) {
            searchBooks();
        }
    } catch (err) {
        console.error('Borrow error:', err);
        closeBorrowModal();
        alert('Something went wrong. Please try again.');
    }
}

modalConfirm.addEventListener('click', confirmBorrow);
modalCancel.addEventListener('click', closeBorrowModal);

/* ---------- events ---------- */

searchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    searchBooks();
});

/* ---------- init ---------- */

loadCategories();
searchBooks();
