// Pagelock Admin JavaScript

jQuery(document).ready(function ($) {
  "use strict";

  // Add admin class to body for styling
  $("body").addClass("pagelock-admin");

  // Enhanced form handling
  const $form = $("#pagelock-form");
  const $submitBtn = $("#submit");
  const $passwordField = $("#lock_password");
  const $pagesSelect = $("#lock_pages");

  if ($form.length) {
    // Improve multiple select UX
    if ($pagesSelect.length) {
      // Add search functionality for pages
      addPageSearch();

      // Add select all / deselect all buttons
      addSelectAllButtons();
    }

    // Password strength indicator
    if ($passwordField.length) {
      addPasswordStrengthIndicator();
    }

    // Form validation
    $form.on("submit", function (e) {
      if (!validateForm()) {
        e.preventDefault();
        return false;
      }

      // Show loading state
      $submitBtn.addClass("pagelock-loading").prop("disabled", true);

      // Change button text
      const originalText = $submitBtn.val();
      $submitBtn.val("Saving...");

      // Handle form submission via AJAX (already handled in PHP, but add error handling)
      setTimeout(function () {
        if ($submitBtn.hasClass("pagelock-loading")) {
          $submitBtn.removeClass("pagelock-loading").prop("disabled", false);
          $submitBtn.val(originalText);
        }
      }, 10000); // Fallback timeout
    });
  }

  // Enhanced table interactions
  const $table = $(".wp-list-table");
  if ($table.length) {
    // Add hover effects and improve accessibility
    $table.find("tbody tr").each(function () {
      const $row = $(this);

      $row
        .on("mouseenter", function () {
          $(this).css("background-color", "#f8fdf8");
        })
        .on("mouseleave", function () {
          $(this).css("background-color", "");
        });
    });

    // Improve delete confirmations
    $table.find('a[onclick*="confirm"]').each(function () {
      const $link = $(this);
      $link.removeAttr("onclick").on("click", function (e) {
        e.preventDefault();

        showDeleteConfirmation(function () {
          window.location.href = $link.attr("href");
        });
      });
    });
  }

  function addPageSearch() {
    const $container = $pagesSelect.parent();
    const $searchInput = $(
      '<input type="text" placeholder="Search pages..." class="pagelock-page-search regular-text" style="margin-bottom: 10px;">'
    );

    $searchInput.insertBefore($pagesSelect);

    $searchInput.on("input", function () {
      const searchTerm = $(this).val().toLowerCase();

      $pagesSelect.find("option").each(function () {
        const $option = $(this);
        const pageTitle = $option.text().toLowerCase();

        if (pageTitle.includes(searchTerm)) {
          $option.show();
        } else {
          $option.hide();
        }
      });
    });
  }

  function addSelectAllButtons() {
    const $container = $pagesSelect.parent();
    const $buttonContainer = $(
      '<div class="pagelock-select-buttons" style="margin-top: 10px;"></div>'
    );
    const $selectAllBtn = $(
      '<button type="button" class="button">Select All</button>'
    );
    const $deselectAllBtn = $(
      '<button type="button" class="button" style="margin-left: 5px;">Deselect All</button>'
    );

    $buttonContainer.append($selectAllBtn).append($deselectAllBtn);
    $buttonContainer.insertAfter($pagesSelect);

    $selectAllBtn.on("click", function (e) {
      e.preventDefault();
      $pagesSelect.find("option:visible").prop("selected", true);
    });

    $deselectAllBtn.on("click", function (e) {
      e.preventDefault();
      $pagesSelect.find("option").prop("selected", false);
    });
  }

  function addPasswordStrengthIndicator() {
    const $container = $passwordField.parent();
    const $indicator = $(
      '<div class="pagelock-password-strength"><div class="strength-bar"><div class="strength-fill"></div></div><div class="strength-text">Enter a password</div></div>'
    );

    $indicator.insertAfter($passwordField);

    // Add CSS for strength indicator
    const strengthCSS = `
            <style>
            .pagelock-password-strength {
                margin-top: 10px;
            }
            .strength-bar {
                height: 4px;
                background: #e0e0e0;
                border-radius: 2px;
                overflow: hidden;
                margin-bottom: 5px;
            }
            .strength-fill {
                height: 100%;
                width: 0%;
                transition: all 0.3s ease;
                border-radius: 2px;
            }
            .strength-text {
                font-size: 12px;
                color: #666;
            }
            .strength-weak .strength-fill { background: #e74c3c; width: 25%; }
            .strength-fair .strength-fill { background: #f39c12; width: 50%; }
            .strength-good .strength-fill { background: #f1c40f; width: 75%; }
            .strength-strong .strength-fill { background: #27ae60; width: 100%; }
            </style>
        `;
    $("head").append(strengthCSS);

    $passwordField.on("input", function () {
      const password = $(this).val();
      const strength = calculatePasswordStrength(password);

      updatePasswordStrength($indicator, strength);
    });
  }

  function calculatePasswordStrength(password) {
    if (password.length === 0)
      return { level: "none", text: "Enter a password" };
    if (password.length < 6) return { level: "weak", text: "Weak - Too short" };

    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score < 2) return { level: "weak", text: "Weak" };
    if (score < 3) return { level: "fair", text: "Fair" };
    if (score < 4) return { level: "good", text: "Good" };
    return { level: "strong", text: "Strong" };
  }

  function updatePasswordStrength($indicator, strength) {
    $indicator.removeClass(
      "strength-weak strength-fair strength-good strength-strong"
    );
    if (strength.level !== "none") {
      $indicator.addClass("strength-" + strength.level);
    }
    $indicator.find(".strength-text").text(strength.text);
  }

  function validateForm() {
    let isValid = true;

    // Reset previous error states
    $(".pagelock-error").remove();

    // Validate name
    const name = $("#lock_name").val().trim();
    if (!name) {
      showFieldError("#lock_name", "Lock name is required");
      isValid = false;
    }

    // Validate password (only for new locks or if field has value)
    const isEdit = $('input[name="lock_id"]').length > 0;
    const password = $passwordField.val();
    if (!isEdit && !password) {
      showFieldError("#lock_password", "Password is required");
      isValid = false;
    }

    // Validate pages selection
    const selectedPages = $pagesSelect.val();
    if (!selectedPages || selectedPages.length === 0) {
      showFieldError("#lock_pages", "Please select at least one page");
      isValid = false;
    }

    return isValid;
  }

  function showFieldError(fieldSelector, message) {
    const $field = $(fieldSelector);
    const $error = $(
      '<div class="pagelock-error notice notice-error" style="padding: 5px 10px; margin: 5px 0; background: #ffeaea; border-left: 4px solid #dc3232; font-size: 12px;">' +
        message +
        "</div>"
    );
    $error.insertAfter($field);

    $field.focus();
  }

  function showDeleteConfirmation(callback) {
    // Create custom modal instead of browser confirm
    const modal = `
            <div id="pagelock-delete-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    padding: 2rem;
                    border-radius: 8px;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                ">
                    <h3 style="margin-bottom: 1rem; color: #d32f2f;">Delete Page Lock</h3>
                    <p style="margin-bottom: 2rem; color: #666;">Are you sure you want to delete this page lock? This action cannot be undone.</p>
                    <div>
                        <button id="pagelock-confirm-delete" class="button button-primary" style="background: #d32f2f; border-color: #d32f2f; margin-right: 10px;">Delete</button>
                        <button id="pagelock-cancel-delete" class="button">Cancel</button>
                    </div>
                </div>
            </div>
        `;

    $("body").append(modal);

    $("#pagelock-confirm-delete").on("click", function () {
      $("#pagelock-delete-modal").remove();
      callback();
    });

    $("#pagelock-cancel-delete, #pagelock-delete-modal").on(
      "click",
      function (e) {
        if (e.target === this) {
          $("#pagelock-delete-modal").remove();
        }
      }
    );
  }

  // Success message handling
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("message") === "deleted") {
    showNotice("Page lock deleted successfully.", "success");
  } else if (urlParams.get("message") === "error") {
    showNotice("An error occurred while deleting the page lock.", "error");
  }

  function showNotice(message, type) {
    const $notice = $(
      '<div class="notice notice-' +
        type +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );
    $(".wrap h1").after($notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      $notice.fadeOut();
    }, 5000);
  }
});
