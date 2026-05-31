// Simpler sidebar management to ensure consistent behavior
document.addEventListener('DOMContentLoaded', function() {
    // Ensure sidebar is sticky across all pages
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.style.position = 'sticky';
        sidebar.style.top = '52px'; // Updated to account for top bar height
        sidebar.style.height = 'calc(100vh - 52px)'; // Adjusted height
        sidebar.style.overflowY = 'auto';
        sidebar.style.zIndex = '90'; // Add z-index for proper stacking
    }
    
    const menuHeaders = document.querySelectorAll('.menu-header');
    const path = window.location.pathname;
    
    // Log the actual path for debugging
    console.log("Current path:", path);
    
    // Helper function to save dropdown state and which section is open
    function saveDropdownState(isOpen, sectionName) {
        localStorage.setItem('sidebarDropdownState', isOpen ? 'open' : 'closed');
        if (isOpen && sectionName) {
            localStorage.setItem('activeSection', sectionName);
        } else {
            localStorage.removeItem('activeSection');
        }
    }

    // Helper function to get dropdown state
    function getDropdownState() {
        return localStorage.getItem('sidebarDropdownState') === 'open';
    }
    
    // Helper function to get active section
    function getActiveSection() {
        return localStorage.getItem('activeSection');
    }
    
    // Helper function to close all dropdowns
    function closeAllDropdowns() {
        menuHeaders.forEach(header => {
            const dropdownContent = header.nextElementSibling;
            const icon = header.querySelector('.dropdown-icon');
            icon.classList.remove('expanded');
            dropdownContent.classList.remove('show');
        });
        saveDropdownState(false);
    }
    
    // Helper function to open a specific section
    function openSection(sectionName) {
        console.log("Opening section:", sectionName);
        menuHeaders.forEach(header => {
            const label = header.querySelector('span').textContent.trim().toLowerCase();
            console.log("Checking label:", label, "against:", sectionName);
            if (label === sectionName) {
                const dropdownContent = header.nextElementSibling;
                const icon = header.querySelector('.dropdown-icon');
                icon.classList.add('expanded');
                dropdownContent.classList.add('show');
                saveDropdownState(true, sectionName);
            }
        });
    }
    
    // Helper function to determine which section a path belongs to
    function getSectionForPath(path) {
        // Accounts section
        if (path.includes('/registeredaccount/') || 
            path.includes('panel.php') || 
            path.includes('adviser.php')) {
            return 'accounts';
        }
        // Research Proposal section
        else if (path.includes('/titleproposal/') || 
                 path.includes('/student/homepage/titleproposal/') || 
                 path.includes('/adviser/homepage/titleproposal/') || 
                 path.includes('/panel/homepage/titleproposal/')) {
            return 'research proposal';
        }
        // Final Defense section
        else if (path.includes('/final/') || 
                 path.includes('/student/homepage/final/') || 
                 path.includes('/adviser/homepage/final/') || 
                 path.includes('/panel/homepage/final/')) {
            return 'final defense';
        }
        // Department Course section
        else if (path.includes('/departmentcourse/')) {
            return 'department course';
        }
        return null;
    }
    
    // Helper function to set active section based on URL
    function setActiveSection(path) {
        // First close all sections
        closeAllDropdowns();
        
        // Get the section for this path
        const sectionName = getSectionForPath(path);
        
        // If we found a matching section, open it
        if (sectionName) {
            openSection(sectionName);
        }
        
        // Highlight the appropriate submenu for accounts section
        if (sectionName === 'accounts') {
            if (path.includes('panel_register.php') || path.includes('panel.php')) {
                const panelLink = document.querySelector('a[href*="panel_register.php"]');
                if (panelLink) panelLink.classList.add('active');
            } else if (path.includes('adviser_register.php') || path.includes('adviser.php')) {
                const adviserLink = document.querySelector('a[href*="adviser_register.php"]');
                if (adviserLink) adviserLink.classList.add('active');
            } else if (path.includes('student_register.php')) {
                const studentLink = document.querySelector('a[href*="student_register.php"]');
                if (studentLink) studentLink.classList.add('active');
            }
        }
    }
    
    // Helper function to highlight active submenu
    function highlightActiveSubmenu(path) {
        const submenuItems = document.querySelectorAll('.submenu-item');
        submenuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && (path.includes(href) || 
                        (path.includes('panel.php') && href.includes('panel_register.php')) ||
                        (path.includes('adviser.php') && href.includes('adviser_register.php')))) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
    
    // Always determine the active section based on the current URL
    // This ensures only the correct section is open
    setActiveSection(path);
    
    // Add click handlers for all menu headers
    menuHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            // Prevent the click from affecting parent elements
            e.stopPropagation();
            
            const dropdownContent = this.nextElementSibling;
            const icon = this.querySelector('.dropdown-icon');
            const sectionName = this.querySelector('span').textContent.trim().toLowerCase();
            
            // Toggle the clicked dropdown
            const wasOpen = dropdownContent.classList.contains('show');
            
            // Close all other dropdowns (but not this one yet)
            menuHeaders.forEach(h => {
                if (h !== this) {
                    const otherIcon = h.querySelector('.dropdown-icon');
                    const otherContent = h.nextElementSibling;
                    otherIcon.classList.remove('expanded');
                    otherContent.classList.remove('show');
                }
            });
            
            // Now toggle this dropdown
            if (wasOpen) {
                icon.classList.remove('expanded');
                dropdownContent.classList.remove('show');
                saveDropdownState(false, null);
            } else {
                icon.classList.add('expanded');
                dropdownContent.classList.add('show');
                saveDropdownState(true, sectionName);
                
                // Log which section was clicked for debugging
                console.log("Clicked section:", sectionName);
            }
        });
    });
    
    // Prevent submenu items from closing the dropdown when clicked
    const submenuItems = document.querySelectorAll('.submenu-item');
    submenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't let the click bubble up to the parent elements
            e.stopPropagation();
            // Get the parent menu header to identify which section this is
            const parentHeader = this.closest('.submenu').previousElementSibling;
            const sectionName = parentHeader.querySelector('span').textContent.trim().toLowerCase();
            saveDropdownState(true, sectionName); // Keep dropdown open when clicking submenu items
        });
    });
    
    // Highlight active submenu item
    highlightActiveSubmenu(path);
});