// Employee Navigation Component
const EmployeeNavigation = {
  // Employee menu configuration
  menu: {
    sections: [
      {
        title: "My Dashboard",
        items: [
          { href: "workpage.html", icon: "📋", text: "Employee Dashboard" },
          { href: "attendance.html", icon: "📅", text: "My Attendance" }
        ]
      },
      {
        title: "My Requests",
        items: [
          { href: "salary-request.html", icon: "💰📝", text: "Salary Request" },
          { href: "leave.html", icon: "🏖️", text: "Leave Request" }
        ]
      },
      {
        title: "My Profile",
        items: [
          { href: "profile.html", icon: "👤", text: "Profile & Settings" }
        ]
      },
      {
        title: "Company Info",
        items: [
          { href: "../service.html", icon: "🔧", text: "Services" },
          { href: "../contact.html", icon: "📞", text: "Contact Information" }
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
      <button class="menu-btn" onclick="EmployeeNavigation.openNav()">☰</button>
      <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="EmployeeNavigation.closeNav()">&times;</a>
        <div class="nav-header">👥 EMPLOYEE PORTAL</div>
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
    window.location.href = "../login.html";
  },

  // Add logout button
  addLogoutButton: function() {
    const logoutHTML = `<button class="logout-btn" onclick="EmployeeNavigation.logout()">Logout</button>`;
    
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
      const isEmployee = localStorage.getItem("isEmployee");
      
      if (isEmployee !== "true") {
        alert("Access denied. Please log in.");
        window.location.href = "../login.html";
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
          EmployeeNavigation.closeNav();
        }
      }
    });

    // Close navigation on escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        EmployeeNavigation.closeNav();
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
    // Auto-init can be disabled by setting window.EmployeeNavigation_AutoInit = false before including this script
    if (window.EmployeeNavigation_AutoInit !== false) {
      EmployeeNavigation.init();
    }
  });
} else {
  // DOM is already loaded
  if (window.EmployeeNavigation_AutoInit !== false) {
    EmployeeNavigation.init();
  }
}

// Make it globally available
window.EmployeeNavigation = EmployeeNavigation;
