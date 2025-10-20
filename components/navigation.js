// Navigation Component
const NavigationComponent = {
  // Configuration for different navigation menus
  menus: {
    admin: {
      sections: [
        {
          title: "💼 ADMIN SECTION",
          isMainSection: true,
          subsections: [
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
                { href: "disciplinary.html", icon: "⚖️", text: "Disciplinary Actions" }
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
        {
          title: "👥 EMPLOYEE SECTION",
          isMainSection: true,
          subsections: [
            {
              title: "My Dashboard",
              items: [
                { href: "../employee/workpage.html", icon: "📋", text: "Employee Dashboard" },
                { href: "../employee/attendance.html", icon: "📅", text: "Attendance Tracking" }
              ]
            },
            {
              title: "My Requests",
              items: [
                { href: "../employee/salary-request.html", icon: "💰📝", text: "Salary Requests" },
                { href: "../employee/leave.html", icon: "🏖️", text: "Leave Requests" }
              ]
            },
            {
              title: "My Profile",
              items: [
                { href: "../employee/profile.html", icon: "👤", text: "Profile & Settings" }
              ]
            }
          ]
        }
      ]
    },
    employee: {
      sections: [
        {
          title: "Dashboard",
          items: [
            { href: "workpage.html", icon: "👥", text: "My Dashboard" },
            { href: "attendance.html", icon: "📋", text: "My Attendance" }
          ]
        },
        {
          title: "My Requests",
          items: [
            { href: "salary-request.html", icon: "💵", text: "Submit Salary Request" },
            { href: "leave.html", icon: "📅", text: "Request Leave" }
          ]
        },
        {
          title: "My Profile",
          items: [
            { href: "profile.html", icon: "👤", text: "My Profile" }
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
    }
  },

  // Generate navigation HTML
  generateNavHTML: function(menuType = 'admin') {
    const menu = this.menus[menuType] || this.menus.admin;
    
    let menuItemsHTML = '';
    
    // Generate sections
    if (menu.sections) {
      menu.sections.forEach((section, index) => {
        // Check if this is a main section with subsections
        if (section.isMainSection && section.subsections) {
          // Add main section title (Admin Section / Employee Section)
          menuItemsHTML += `<div class="nav-main-section">`;
          menuItemsHTML += `<div class="nav-main-section-title">${section.title}</div>`;
          
          // Add subsections
          section.subsections.forEach((subsection, subIndex) => {
            menuItemsHTML += `<div class="nav-subsection">`;
            menuItemsHTML += `<div class="nav-subsection-title">${subsection.title}</div>`;
            
            // Add subsection items
            subsection.items.forEach(item => {
              menuItemsHTML += `<a href="${item.href}">${item.icon} ${item.text}</a>`;
            });
            
            menuItemsHTML += `</div>`;
            
            // Add divider between subsections (except for last subsection)
            if (subIndex < section.subsections.length - 1) {
              menuItemsHTML += `<div class="nav-subdivider"></div>`;
            }
          });
          
          menuItemsHTML += `</div>`;
          
          // Add divider between main sections
          if (index < menu.sections.length - 1) {
            menuItemsHTML += `<div class="nav-divider"></div>`;
          }
        } else {
          // Regular section without subsections (for backward compatibility)
          menuItemsHTML += `<div class="nav-section">`;
          menuItemsHTML += `<div class="nav-section-title">${section.title}</div>`;
          
          // Add section items
          if (section.items) {
            section.items.forEach(item => {
              menuItemsHTML += `<a href="${item.href}">${item.icon} ${item.text}</a>`;
            });
          }
          
          menuItemsHTML += `</div>`;
          
          // Add divider between sections (except for last section)
          if (index < menu.sections.length - 1) {
            menuItemsHTML += `<div class="nav-divider"></div>`;
          }
        }
      });
    } else {
      // Fallback for old format
      const menuItems = menu || [];
      menuItemsHTML = menuItems.map(item => 
        `<a href="${item.href}">${item.icon} ${item.text}</a>`
      ).join('');
    }

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
    localStorage.removeItem("isEmployee");
    localStorage.removeItem("loggedInEmployee");
    localStorage.removeItem("currentEmployeeName");
    localStorage.removeItem("loginTime");
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
      const isEmployee = localStorage.getItem("isEmployee");
      
      if (menuType === 'admin' && isAdmin !== "true") {
        alert("Access denied. Admins only.");
        window.location.href = "../admin-login.html";
        return;
      }
      
      if (menuType === 'employee' && isEmployee !== "true") {
        alert("Access denied. Please log in.");
        window.location.href = "../login.html";
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