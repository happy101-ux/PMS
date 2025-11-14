// Load module dynamically into main content
const mainContent = document.getElementById("mainContent");

document.querySelectorAll(".sidebar-link").forEach(link => {
  link.addEventListener("click", function (e) {
    e.preventDefault();
    fetch(this.getAttribute("href"))
      .then(response => response.text())
      .then(html => {
        mainContent.innerHTML = html;
      })
      .catch(err => console.error("Error loading module:", err));
  });
});


/**
 * Officer Modals JS
 * Handles Add, View, Edit, Delete, and Revoke officer actions
 * Uses fetch for AJAX calls to PHP scripts
 * Ensures modals are reusable and centered
 */

// Get overlay elements
const overlay = document.getElementById('overlayModal');
const overlayBody = document.getElementById('overlayBody');

/**
 * Templates mapping
 * Key: sidebar href URL
 * Value: template ID to show in overlay
 */
const templates = {
  'add_staff_content.php': 'addStaffFormTemplate',
  'view_officer.php': 'viewOfficerFormTemplate'
};

/**
 * Open a modal overlay with a given template ID
 * @param {string} templateId - ID of the hidden template
 */
function openTemplateOverlay(templateId) {
  overlayBody.innerHTML = document.getElementById(templateId).innerHTML;
  overlay.classList.add('active');

  // Close button inside template
  const closeBtn = overlayBody.querySelector('.close-overlay-btn');
  if (closeBtn) closeBtn.addEventListener('click', closeOverlay);

  // Click outside modal to close
  overlay.addEventListener('click', ev => {
    if (ev.target === overlay) closeOverlay();
  });

  // Password autofill for Add Officer
  const officerInput = overlayBody.querySelector('#officeridInput');
  const passwordInput = overlayBody.querySelector('#passwordInput');
  const togglePasswordBtn = overlayBody.querySelector('#togglePassword');

  if (officerInput && passwordInput) {
    officerInput.addEventListener('input', () => passwordInput.value = officerInput.value);
  }

  if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener('click', () => {
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        togglePasswordBtn.querySelector('i').classList.replace('bi-eye','bi-eye-slash');
      } else {
        passwordInput.type = 'password';
        togglePasswordBtn.querySelector('i').classList.replace('bi-eye-slash','bi-eye');
      }
    });
  }

  // Attach officer action handlers (view/edit/delete/revoke)
  attachOfficerActions();
}

/**
 * Close overlay
 */
function closeOverlay() {
  overlay.classList.remove('active');
  overlayBody.innerHTML = ''; // Clear content
}

/**
 * Attach action buttons (Edit, Delete, Revoke) inside the officer table
 */
function attachOfficerActions() {
  // Edit Officer
  overlayBody.querySelectorAll('.btn-primary').forEach(btn => {
    btn.addEventListener('click', function() {
      const officerId = this.getAttribute('data-officerid');
      openEditModal(officerId);
    });
  });

  // Delete Officer
  overlayBody.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('click', function() {
      const officerId = this.getAttribute('data-officerid');
      deleteOfficer(officerId);
    });
  });

  // Revoke Officer
  overlayBody.querySelectorAll('.btn-warning').forEach(btn => {
    btn.addEventListener('click', function() {
      const officerId = this.getAttribute('data-officerid');
      openRevokeModal(officerId);
    });
  });

  // Edit form submission inside overlay
  const editForm = overlayBody.querySelector('#editOfficerForm');
  if (editForm) {
    editForm.addEventListener('submit', function(e){
      e.preventDefault();
      const data = new URLSearchParams(new FormData(this));
      fetch('update_officer.php', { method:'POST', body: data })
        .then(res => res.text())
        .then(res => {
          alert(res);
          closeOverlay();
          location.reload();
        });
    });
  }
}

/**
 * Open Edit Officer Modal
 * @param {string} officerId 
 */
function openEditModal(officerId) {
  fetch('get_officer.php?id=' + officerId)
    .then(res => res.json())
    .then(data => {
      // Populate hidden overlay template fields
      const editModal = overlayBody.querySelector('#editOfficerModal');
      if (!editModal) return;

      editModal.querySelector('#editOfficerId').value = data.officerid;
      editModal.querySelector('#editFirstName').value = data.first_name;
      editModal.querySelector('#editLastName').value = data.last_name;
      editModal.querySelector('#editStatus').value = data.status;

      editModal.style.display = 'flex';
    });
}

/**
 * Open Revoke Officer Modal
 * @param {string} officerId
 */
function openRevokeModal(officerId) {
  const revokeModal = overlayBody.querySelector('#revokeOfficerModal');
  if (!revokeModal) return;

  revokeModal.querySelector('#revokeText').innerText =
    `Are you sure you want to revoke access for officer ${officerId}?`;

  revokeModal.style.display = 'flex';

  revokeModal.querySelector('#confirmRevokeBtn').onclick = function() {
    fetch('revoke_officer.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'officerid=' + officerId
    }).then(res => res.text())
      .then(res => {
        alert(res);
        closeOverlay();
        location.reload();
      });
  };
}

/**
 * Delete Officer
 * @param {string} officerId
 */
function deleteOfficer(officerId) {
  if (!confirm(`Are you sure you want to delete officer ${officerId}?`)) return;

  fetch('delete_officer.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'officerid=' + officerId
  }).then(res => res.text())
    .then(res => {
      alert(res);
      location.reload();
    });
}

/**
 * Sidebar link clicks: open overlay if template exists
 */
document.querySelectorAll('.sidebar-link').forEach(link => {
  link.addEventListener('click', function(e){
    e.preventDefault();
    const url = this.getAttribute('href');

    if (templates[url]) {
      openTemplateOverlay(templates[url]);
    } else {
      // Fetch page normally
      fetch(url)
        .then(res => res.text())
        .then(data => document.getElementById('mainContent').innerHTML = data)
        .catch(err => console.error(err));
    }
  });
});

/**
 * Sidebar collapse toggle
 */
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('full-width');
  });
}
