// Admin Navigation Component
const AdminNavigation = {
  // Admin menu configuration
  menu: {
    sections: [
      {
        title: "Records & Dashboard",
        items: [
          { href: "dashboard.html", icon: "📊", text: "Employee Records" },
          { href: "reports.html", icon: "📝", text: "Reports" },
          { href: "budget.html", icon: "💼", text: "Budget Management" }
        ]
      },
      {
        title: "Employee Management",
        items: [
          { href: "add-employee.html", icon: "👤➕", text: "Add Employee" },
          { href: "evaluation-form.html", icon: "📊", text: "Employee Evaluation" },
          { href: "evaluations-view.html", icon: "⭐", text: "View Evaluations" },
          { href: "disciplinary.html", icon: "⚖️", text: "Disciplinary Actions" },
          { href: "performance.html", icon: "📈", text: "Performance" }
        ]
      },
      {
        title: "Payroll & Benefits",
        items: [
          { href: "payslip.html", icon: "💰", text: "Payslip Management" },
          { href: "add-payslip.html", icon: "💰➕", text: "Add Payslip" },
          { href: "benefits.html", icon: "🎁", text: "Employee Benefits" }
        ]
      },
      {
        title: "Requests Management",
        items: [
          { href: "overtime.html", icon: "⏰", text: "Overtime Requests" },
          { href: "overtime-management.html", icon: "⏰✅", text: "Overtime Management" },
          { href: "leave-request.html", icon: "🏖️📝", text: "Leave Requests" },
          { href: "leave-management.html", icon: "📋✅", text: "Leave Management" }
        ]
      },
      {
        title: "Training & Development",
        items: [
          { href: "training.html", icon: "🎓", text: "Training Programs" }
        ]
      }
    ]
  },

  // Generate navigation HTML
  generateNavHTML: function() {
    let menuItemsHTML = '';
    
    this.menu.sections.forEach((section, index) => {
      menuItemsHTML += `<div class="nav-section">`;
      menuItemsHTML += `<div class="nav-section-title">${section.title}</div>`;
      
      // Add section items
      section.items.forEach(item => {
        menuItemsHTML += `<a href="${item.href}">${item.icon} ${item.text}</a>`;
      });
      
      menuItemsHTML += `</div>`;
      
      // Add divider between sections (except for last section)
      if (index < this.menu.sections.length - 1) {
        menuItemsHTML += `<div class="nav-divider"></div>`;
      }
    });

    return `
      <button class="menu-btn" onclick="AdminNavigation.openNav()">☰</button>
      <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="AdminNavigation.closeNav()">&times;</a>
        <div class="nav-header">💼 ADMIN PANEL</div>
        ${menuItemsHTML}
      </div>
    `;
  },

  // Insert navigation into the page
  insertNavigation: function(containerId = 'navigation-container') {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = this.generateNavHTML();
    } else {
      // If no container specified, insert at beginning of body
      const navElement = document.createElement('div');
      navElement.innerHTML = this.generateNavHTML();
      document.body.insertBefore(navElement, document.body.firstChild);
    }
  },

  // Navigation functions
  openNav: function() {
    const sidebar = document.getElementById("mySidebar");
    if (sidebar) {
      sidebar.style.width = "250px";
    }
  },

  closeNav: function() {
    const sidebar = document.getElementById("mySidebar");
    if (sidebar) {
      sidebar.style.width = "0";
    }
  },

  // Logout functionality
  logout: function() {
    localStorage.removeItem("isAdmin");
    localStorage.removeItem("isEmployee");
    localStorage.removeItem("loggedInEmployee");
    localStorage.removeItem("currentEmployeeName");
    localStorage.removeItem("loginTime");
    localStorage.removeItem("userType");
    window.location.href = "../admin-login.html";
  },

  // Add logout button
  addLogoutButton: function() {
    const logoutHTML = `<button class="logout-btn" onclick="AdminNavigation.logout()">Logout</button>`;
    
    // Try to find existing logout button, if not create one
    let logoutBtn = document.querySelector('.logout-btn');
    if (!logoutBtn) {
      const menuBtn = document.querySelector('.menu-btn');
      if (menuBtn) {
        menuBtn.insertAdjacentHTML('afterend', logoutHTML);
      } else {
        document.body.insertAdjacentHTML('afterbegin', logoutHTML);
      }
    }
  },

  // Initialize navigation component
  init: function(options = {}) {
    const {
      containerId = null,
      includeLogout = true,
      authCheck = true
    } = options;

    // Check authentication if enabled
    if (authCheck) {
      const isAdmin = localStorage.getItem("isAdmin");
      
      if (isAdmin !== "true") {
        alert("Access denied. Admins only.");
        window.location.href = "../admin-login.html";
        return;
      }
    }

    // Insert navigation
    this.insertNavigation(containerId);

    // Add logout button if requested
    if (includeLogout) {
      this.addLogoutButton();
    }

    // Add click outside to close functionality
    this.addClickOutsideHandler();
  },

  // Add click outside handler to close navigation
  addClickOutsideHandler: function() {
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById("mySidebar");
      const menuBtn = document.querySelector(".menu-btn");
      
      if (sidebar && sidebar.style.width === "250px") {
        if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
          AdminNavigation.closeNav();
        }
      }
    });

    // Close navigation on escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        AdminNavigation.closeNav();
      }
    });
  },

  // Helper method to set active navigation item
  setActiveNavItem: function(currentPage) {
    // Wait a bit for navigation to be inserted
    setTimeout(() => {
      const navLinks = document.querySelectorAll('#mySidebar a');
      navLinks.forEach(link => {
        if (link.href.includes(currentPage)) {
          link.classList.add('active');
        }
      });
    }, 100);
  }
};

// Auto-initialize if DOM is already loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    // Auto-init can be disabled by setting window.AdminNavigation_AutoInit = false before including this script
    if (window.AdminNavigation_AutoInit !== false) {
      AdminNavigation.init();
    }
  });
} else {
  // DOM is already loaded
  if (window.AdminNavigation_AutoInit !== false) {
    AdminNavigation.init();
  }
}

// Make it globally available
window.AdminNavigation = AdminNavigation;
