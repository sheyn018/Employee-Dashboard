// Navigation Component for HR Connect System
// Usage: Include this script and call NavigationComponent.init() after page load

const NavigationComponent = {
  // Configuration for different navigation menus
  menus: {
    admin: [
      { href: "admin-welcome.html", icon: "🏠", text: "Dashboard" },
      { href: "dashboard.html", icon: "📊", text: "Employee Records" },
      { href: "employee-list.html", icon: "👥", text: "Employee List" },
      { href: "add-employee.html", icon: "➕", text: "Add Employee" },
      { href: "applicant.html", icon: "📝", text: "Applicant" },
      { href: "payslip.html", icon: "💰", text: "Payslip" }
    ],
    employee: [
      { href: "dashboard.html", icon: "📊", text: "Dashboard" },
      { href: "employees.html", icon: "👥", text: "Employees" },
      { href: "new-employee.html", icon: "➕", text: "Add Employee" },
      { href: "payslips.html", icon: "💰", text: "Payslips" }
    ]
  },

  // Generate navigation HTML
  generateNavHTML: function(menuType = 'admin') {
    const menuItems = this.menus[menuType] || this.menus.admin;
    
    const menuItemsHTML = menuItems.map(item => 
      `<a href="${item.href}">${item.icon} ${item.text}</a>`
    ).join('');

    return `
      <button class="menu-btn" onclick="NavigationComponent.openNav()">☰</button>
      <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="NavigationComponent.closeNav()">&times;</a>
        ${menuItemsHTML}
      </div>
    `;
  },

  // Insert navigation into the page
  insertNavigation: function(containerId = 'navigation-container', menuType = 'admin') {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = this.generateNavHTML(menuType);
    } else {
      // If no container specified, insert at beginning of body
      const navElement = document.createElement('div');
      navElement.innerHTML = this.generateNavHTML(menuType);
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
    localStorage.removeItem("userType");
    window.location.href = "../index.html";
  },

  // Add logout button
  addLogoutButton: function() {
    const logoutHTML = `<button class="logout-btn" onclick="NavigationComponent.logout()">Logout</button>`;
    
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
      menuType = 'admin',
      includeLogout = true,
      authCheck = true
    } = options;

    // Check authentication if enabled
    if (authCheck) {
      const isAdmin = localStorage.getItem("isAdmin");
      if (isAdmin !== "true") {
        alert("Access denied. Admins only.");
        window.location.href = "admin-login.html";
        return;
      }
    }

    // Insert navigation
    this.insertNavigation(containerId, menuType);

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
          NavigationComponent.closeNav();
        }
      }
    });

    // Close navigation on escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        NavigationComponent.closeNav();
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
    // Auto-init can be disabled by setting window.NavigationComponent_AutoInit = false before including this script
    if (window.NavigationComponent_AutoInit !== false) {
      NavigationComponent.init();
    }
  });
} else {
  // DOM is already loaded
  if (window.NavigationComponent_AutoInit !== false) {
    NavigationComponent.init();
  }
}

// Make it globally available
window.NavigationComponent = NavigationComponent;