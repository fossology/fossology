/**
 * SPDX-FileCopyrightText: © 2024 FOSSology contributors
 * SPDX-License-Identifier: FSFAP
 *
 * Logic for cycling through evidence and context menu operations in UI View.
 */

var EvidenceCycler = {
  types: {
    'copyright': '.hi-cp',
    'email': '.hi-email',
    'url': '.hi-url',
    'license': '.hi-match'
  },
  currentIndex: {
    'copyright': -1,
    'email': -1,
    'url': -1,
    'license': -1
  },

  init: function () {
    this.addStyles();
    this.createToolbar();
    this.createContextMenu();

    document.addEventListener('contextmenu', (e) => {
      const target = e.target;
      if (target.matches('.hi-cp, .hi-email, .hi-url, .hi-match')) {
        this.showContextMenu(e, target);
      }
    });

    document.addEventListener('click', (e) => {
      const menu = document.getElementById('evidence-context-menu');
      if (menu && menu.style.display === 'block') {
        menu.style.display = 'none';
      }
    });
  },

  addStyles: function () {
    const style = document.createElement('style');
    style.textContent = `
      #evidence-toolbar {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 13px;
        max-width: 320px;
        backdrop-filter: blur(5px);
      }
      .evidence-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f3f4f6;
      }
      .evidence-group:last-child {
        margin-bottom: 0;
        border-bottom: none;
        padding-bottom: 0;
      }
      .evidence-group span {
        font-weight: 600;
        color: #374151;
        margin-right: 12px;
        flex-grow: 1;
      }
      .evidence-group button {
        margin-left: 6px;
        padding: 4px 10px;
        cursor: pointer;
        background: #f9fafb;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        transition: all 0.2s;
        font-size: 14px;
        line-height: 1;
        color: #4b5563;
      }
      .evidence-group button:hover {
        background: #e5e7eb;
        color: #111827;
        border-color: #9ca3af;
      }
      .evidence-group button:active {
        background: #d1d5db;
      }
      .active-highlight {
        outline: 3px solid #f59e0b !important;
        background-color: rgba(245, 158, 11, 0.3) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);
        border-radius: 2px;
      }
      #evidence-context-menu {
        display: none;
        position: absolute;
        background: white;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        z-index: 1001;
        min-width: 180px;
        border-radius: 6px;
        padding: 4px 0;
      }
      #evidence-context-menu ul {
        list-style: none;
        margin: 0;
        padding: 0;
      }
      #evidence-context-menu li {
        padding: 10px 16px;
        cursor: pointer;
        font-size: 14px;
        color: #374151;
        display: flex;
        align-items: center;
        transition: background-color 0.1s;
      }
      #evidence-context-menu li:hover {
        background-color: #f3f4f6;
        color: #111827;
      }
      #evidence-toolbar-header {
        font-weight: 700;
        margin-bottom: 10px;
        text-align: center;
        color: #111827;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 5px;
      }
    `;
    document.head.appendChild(style);
  },

  createToolbar: function () {
    const toolbar = document.createElement('div');
    toolbar.id = 'evidence-toolbar';
    toolbar.innerHTML = `
      <div id="evidence-toolbar-header">Evidence Navigation</div>
      <div class="evidence-group">
        <span>Copyrights</span>
        <button onclick="EvidenceCycler.cycle('copyright', -1)" title="Previous Copyright">&#8592;</button>
        <button onclick="EvidenceCycler.cycle('copyright', 1)" title="Next Copyright">&#8594;</button>
      </div>
      <div class="evidence-group">
        <span>Emails</span>
        <button onclick="EvidenceCycler.cycle('email', -1)" title="Previous Email">&#8592;</button>
        <button onclick="EvidenceCycler.cycle('email', 1)" title="Next Email">&#8594;</button>
      </div>
      <div class="evidence-group">
        <span>URLs</span>
        <button onclick="EvidenceCycler.cycle('url', -1)" title="Previous URL">&#8592;</button>
        <button onclick="EvidenceCycler.cycle('url', 1)" title="Next URL">&#8594;</button>
      </div>
      <div class="evidence-group">
        <span>Licenses</span>
        <button onclick="EvidenceCycler.cycle('license', -1)" title="Previous License">&#8592;</button>
        <button onclick="EvidenceCycler.cycle('license', 1)" title="Next License">&#8594;</button>
      </div>
    `;
    document.body.appendChild(toolbar);
  },

  createContextMenu: function () {
    const menu = document.createElement('div');
    menu.id = 'evidence-context-menu';
    document.body.appendChild(menu);
  },

  cycle: function (type, direction) {
    const selector = this.types[type];
    const elements = document.querySelectorAll(selector);

    if (elements.length === 0) {
      alert('No evidence of type ' + type + ' found.');
      return;
    }

    if (this.currentIndex[type] !== -1 && elements[this.currentIndex[type]]) {
      elements[this.currentIndex[type]].classList.remove('active-highlight');
    }

    this.currentIndex[type] += direction;

    if (this.currentIndex[type] >= elements.length) {
      this.currentIndex[type] = 0;
    } else if (this.currentIndex[type] < 0) {
      this.currentIndex[type] = elements.length - 1;
    }

    const el = elements[this.currentIndex[type]];
    el.classList.add('active-highlight');
    el.scrollIntoView({ behavior: "smooth", block: "center" });
  },

  showContextMenu: function (e, element) {
    e.preventDefault();
    this.activeElement = element;
    const menu = document.getElementById('evidence-context-menu');

    let menuHtml = '<ul>';
    const text = element.textContent.trim();

    menuHtml += `<li onclick="EvidenceCycler.copySelection()">Copy Value</li>`;

    if (element.classList.contains('hi-url')) {
      let url = text;
      if (!/^https?:\/\//i.test(url) && !/^ftp:\/\//i.test(url)) {
        url = 'http://' + url;
      }
      menuHtml += `<li onclick="window.open('${url.replace(/'/g, "\\'")}', '_blank')">Open Link in New Tab</li>`;
    }

    menuHtml += '</ul>';
    menu.innerHTML = menuHtml;

    menu.style.display = 'block';

    let x = e.pageX;
    let y = e.pageY;

    const menuWidth = 180;
    if (x + menuWidth > window.innerWidth) {
      x -= menuWidth;
    }

    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
  },

  copySelection: function () {
    if (this.activeElement) {
      const text = this.activeElement.textContent;
      navigator.clipboard.writeText(text).then(() => {
        // clipboard access successful
      }).catch(err => {
        console.error('Failed to copy: ', err);
      });
      document.getElementById('evidence-context-menu').style.display = 'none';
    }
  }
};

document.addEventListener('DOMContentLoaded', function () {
  EvidenceCycler.init();
});
