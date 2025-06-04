// Main JavaScript file for SkillVerge
document.addEventListener("DOMContentLoaded", () => {
  // Import Bootstrap
  const bootstrap = window.bootstrap

  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))

  // Initialize popovers
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })

  // Mobile sidebar toggle
  const sidebarToggle = document.getElementById("sidebarToggle")
  const sidebar = document.querySelector(".dashboard-sidebar")

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("show")
    })
  }

  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0"
      setTimeout(() => {
        alert.remove()
      }, 300)
    }, 5000)
  })

  // Mobile menu toggle
  function initMobileMenu() {
    // Create mobile menu toggle if it doesn't exist
    if (window.innerWidth <= 768 && !document.getElementById("mobile-menu-toggle")) {
      const sidebar = document.querySelector(".dashboard-sidebar")
      if (sidebar) {
        const toggleBtn = document.createElement("button")
        toggleBtn.id = "mobile-menu-toggle"
        toggleBtn.className = "mobile-menu-toggle"
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>'
        toggleBtn.addEventListener("click", () => {
          sidebar.classList.toggle("show")
        })
        document.body.appendChild(toggleBtn)

        // Close sidebar when clicking outside
        document.addEventListener("click", (e) => {
          if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove("show")
          }
        })
      }
    }
  }

  // Initialize mobile menu
  initMobileMenu()

  // Reinitialize on window resize
  window.addEventListener("resize", () => {
    const existingToggle = document.getElementById("mobile-menu-toggle")
    if (window.innerWidth > 768 && existingToggle) {
      existingToggle.remove()
      document.querySelector(".dashboard-sidebar")?.classList.remove("show")
    } else if (window.innerWidth <= 768 && !existingToggle) {
      initMobileMenu()
    }
  })

  // Fix form validation
  const forms = document.querySelectorAll(".needs-validation")
  forms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()

        // Focus on first invalid field
        const firstInvalid = form.querySelector(":invalid")
        if (firstInvalid) {
          firstInvalid.focus()
        }
      }
      form.classList.add("was-validated")
    })
  })

  // Fix loading states for buttons
  document.addEventListener("click", (e) => {
    if (e.target.classList.contains("btn-loading")) {
      const btn = e.target
      const originalText = btn.innerHTML

      // Prevent double submission
      if (btn.disabled) return

      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...'
      btn.disabled = true

      // Re-enable after 5 seconds (safety measure)
      setTimeout(() => {
        if (btn.disabled) {
          btn.innerHTML = originalText
          btn.disabled = false
        }
      }, 5000)
    }
  })

  // Fix copy to clipboard functionality
  window.copyToClipboard = (text) => {
    if (!text) {
      window.showToast("Nothing to copy!", "error")
      return
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard
        .writeText(text)
        .then(() => window.showToast("Copied to clipboard!", "success"))
        .catch(() => fallbackCopyTextToClipboard(text))
    } else {
      fallbackCopyTextToClipboard(text)
    }
  }

  function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea")
    textArea.value = text
    textArea.style.position = "fixed"
    textArea.style.left = "-999999px"
    textArea.style.top = "-999999px"
    document.body.appendChild(textArea)
    textArea.focus()
    textArea.select()

    try {
      document.execCommand("copy")
      window.showToast("Copied to clipboard!", "success")
    } catch (err) {
      window.showToast("Failed to copy text", "error")
    }

    document.body.removeChild(textArea)
  }

  // Enhanced toast notification system
  window.showToast = (message, type = "info") => {
    if (!message) return

    const toastContainer = getOrCreateToastContainer()
    const toast = createToast(message, type)
    toastContainer.appendChild(toast)

    const bsToast = new bootstrap.Toast(toast, {
      autohide: true,
      delay: type === "error" ? 5000 : 3000,
    })
    bsToast.show()

    toast.addEventListener("hidden.bs.toast", () => {
      toast.remove()
    })
  }

  function getOrCreateToastContainer() {
    let container = document.getElementById("toast-container")
    if (!container) {
      container = document.createElement("div")
      container.id = "toast-container"
      container.className = "toast-container position-fixed top-0 end-0 p-3"
      container.style.zIndex = "9999"
      document.body.appendChild(container)
    }
    return container
  }

  function createToast(message, type) {
    const toast = document.createElement("div")
    toast.className = "toast"
    toast.setAttribute("role", "alert")

    const iconMap = {
      success: "fas fa-check-circle text-success",
      error: "fas fa-exclamation-circle text-danger",
      warning: "fas fa-exclamation-triangle text-warning",
      info: "fas fa-info-circle text-info",
    }

    toast.innerHTML = `
      <div class="toast-header">
        <i class="${iconMap[type] || iconMap.info} me-2"></i>
        <strong class="me-auto">SkillVerge</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        ${message}
      </div>
    `

    return toast
  }

  // Lazy loading for images
  const images = document.querySelectorAll("img[data-src]")
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target
        img.src = img.dataset.src
        img.classList.remove("lazy")
        imageObserver.unobserve(img)
      }
    })
  })

  images.forEach((img) => imageObserver.observe(img))

  // Auto-save form data to localStorage
  const autoSaveForms = document.querySelectorAll(".auto-save")
  autoSaveForms.forEach((form) => {
    const formId = form.id
    if (!formId) return

    // Load saved data
    const savedData = localStorage.getItem(`form_${formId}`)
    if (savedData) {
      const data = JSON.parse(savedData)
      Object.keys(data).forEach((key) => {
        const field = form.querySelector(`[name="${key}"]`)
        if (field) {
          field.value = data[key]
        }
      })
    }

    // Save data on input
    form.addEventListener("input", () => {
      const formData = new FormData(form)
      const data = {}
      for (const [key, value] of formData.entries()) {
        data[key] = value
      }
      localStorage.setItem(`form_${formId}`, JSON.stringify(data))
    })

    // Clear saved data on successful submit
    form.addEventListener("submit", () => {
      localStorage.removeItem(`form_${formId}`)
    })
  })

  // Keyboard shortcuts
  document.addEventListener("keydown", (e) => {
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === "k") {
      e.preventDefault()
      const searchInput = document.querySelector("#search-input, .search-input")
      if (searchInput) {
        searchInput.focus()
      }
    }

    // Escape to close modals
    if (e.key === "Escape") {
      const openModal = document.querySelector(".modal.show")
      if (openModal) {
        const modal = bootstrap.Modal.getInstance(openModal)
        if (modal) modal.hide()
      }
    }
  })

  // Progress bar animation
  const progressBars = document.querySelectorAll(".progress-bar[data-animate]")
  const progressObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const bar = entry.target
        const width = bar.getAttribute("aria-valuenow") + "%"
        bar.style.width = width
        progressObserver.unobserve(bar)
      }
    })
  })

  progressBars.forEach((bar) => {
    bar.style.width = "0%"
    progressObserver.observe(bar)
  })

  // Count up animation for numbers
  const countElements = document.querySelectorAll(".count-up")
  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const element = entry.target
        const target = Number.parseInt(element.getAttribute("data-target"))
        const duration = Number.parseInt(element.getAttribute("data-duration")) || 2000

        window.animateCount(element, 0, target, duration)
        countObserver.unobserve(element)
      }
    })
  })

  countElements.forEach((el) => countObserver.observe(el))

  function animateCount(element, start, end, duration) {
    const startTime = performance.now()

    function updateCount(currentTime) {
      const elapsed = currentTime - startTime
      const progress = Math.min(elapsed / duration, 1)

      const current = Math.floor(start + (end - start) * progress)
      element.textContent = current.toLocaleString()

      if (progress < 1) {
        requestAnimationFrame(updateCount)
      }
    }

    requestAnimationFrame(updateCount)
  }

  // Dark mode toggle
  const darkModeToggle = document.getElementById("darkModeToggle")
  if (darkModeToggle) {
    const currentTheme = localStorage.getItem("theme") || "light"
    document.documentElement.setAttribute("data-theme", currentTheme)

    darkModeToggle.addEventListener("click", () => {
      const currentTheme = document.documentElement.getAttribute("data-theme")
      const newTheme = currentTheme === "dark" ? "light" : "dark"

      document.documentElement.setAttribute("data-theme", newTheme)
      localStorage.setItem("theme", newTheme)
    })
  }

  // File upload preview
  const fileInputs = document.querySelectorAll('input[type="file"][data-preview]')
  fileInputs.forEach((input) => {
    input.addEventListener("change", function () {
      const file = this.files[0]
      const previewId = this.getAttribute("data-preview")
      const preview = document.getElementById(previewId)

      if (file && preview) {
        const reader = new FileReader()
        reader.onload = (e) => {
          if (file.type.startsWith("image/")) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" alt="Preview">`
          } else {
            preview.innerHTML = `<div class="alert alert-info"><i class="fas fa-file me-2"></i>${file.name}</div>`
          }
        }
        reader.readAsDataURL(file)
      }
    })
  })

  // Initialize AOS (Animate On Scroll) if available
  if (typeof window.AOS !== "undefined") {
    window.AOS.init({
      duration: 800,
      easing: "ease-in-out",
      once: true,
    })
  }
})

// Utility functions
window.SkillVerge = {
  // Format currency
  formatCurrency: (amount, currency = "INR") => {
    const formatter = new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency: currency,
      minimumFractionDigits: 0,
    })
    return formatter.format(amount)
  },

  // Format date
  formatDate: (date, options = {}) => {
    const defaultOptions = {
      year: "numeric",
      month: "short",
      day: "numeric",
    }
    return new Date(date).toLocaleDateString("en-IN", { ...defaultOptions, ...options })
  },

  // Debounce function
  debounce: (func, wait) => {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  },

  // Throttle function
  throttle: (func, limit) => {
    let inThrottle
    return function () {
      const args = arguments

      if (!inThrottle) {
        func.apply(this, args)
        inThrottle = true
        setTimeout(() => (inThrottle = false), limit)
      }
    }
  },

  // API helper
  api: {
    get: (url) =>
      fetch(url, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
        },
      }).then((response) => response.json()),

    post: (url, data) =>
      fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      }).then((response) => response.json()),
  },
}
