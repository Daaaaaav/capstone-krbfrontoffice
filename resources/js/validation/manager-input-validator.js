/**
 * Manager Input Validation Utility
 * 
 * Provides client-side validation and character filtering for Manager forms.
 * This works alongside server-side Laravel validation to provide immediate feedback.
 * 
 * Features:
 * - Block special characters in text fields
 * - Limit @ symbols in email fields (max 1)
 * - Prevent consecutive dots in email fields
 * - Limit + symbols in phone fields (max 1, only at start)
 * - Limit - symbols in phone fields (max 4)
 * - Works with typing, pasting, and autocomplete
 */

// const ManagerInputValidator = {
    /**
     * Blocked special characters for general text fields
     * Allows: letters, numbers, spaces, basic punctuation (. , - ' ")
     */
    // blockedChars: ['<', '>', '{', '}', '[', ']', '|', '\\', '/', ';', ':', '`', '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '=', '+', '?'],

    /**
     * Initialize validation on a text input field
     * @param {HTMLInputElement} input - The input element to validate
     * @param {Object} options - Configuration options
     */
//     initTextInput(input, options = {}) {
//         const defaults = {
//             allowedChars: null, // Array of additional allowed characters
//             blockedChars: this.blockedChars,
//             showFeedback: true
//         };
//         const config = { ...defaults, ...options };

//         // Filter input on keypress
//         input.addEventListener('keypress', (e) => {
//             const char = e.key;
            
//             // Allow control keys
//             if (e.ctrlKey || e.metaKey || char.length > 1) {
//                 return;
//             }

//             // Check if character is blocked
//             if (config.blockedChars.includes(char)) {
//                 e.preventDefault();
//                 if (config.showFeedback) {
//                     this.showCharacterBlockedFeedback(input, char);
//                 }
//                 return false;
//             }
//         });

//         // Filter pasted content
//         input.addEventListener('paste', (e) => {
//             e.preventDefault();
//             const pastedText = (e.clipboardData || window.clipboardData).getData('text');
//             const cleaned = this.cleanTextInput(pastedText, config.blockedChars);
            
//             // Insert cleaned text at cursor position
//             const start = input.selectionStart;
//             const end = input.selectionEnd;
//             const currentValue = input.value;
//             const newValue = currentValue.substring(0, start) + cleaned + currentValue.substring(end);
            
//             input.value = newValue;
//             input.setSelectionRange(start + cleaned.length, start + cleaned.length);
            
//             // Trigger input event for Livewire
//             input.dispatchEvent(new Event('input', { bubbles: true }));
            
//             if (cleaned !== pastedText && config.showFeedback) {
//                 this.showPasteCleanedFeedback(input);
//             }
//         });

//         // Also filter on input (for autocomplete, etc.)
//         input.addEventListener('input', (e) => {
//             const cleaned = this.cleanTextInput(input.value, config.blockedChars);
//             if (cleaned !== input.value) {
//                 const cursorPos = input.selectionStart;
//                 input.value = cleaned;
//                 input.setSelectionRange(cursorPos, cursorPos);
//             }
//         });
//     },

//     /**
//      * Initialize validation on an email input field
//      * @param {HTMLInputElement} input - The email input element
//      */
//     initEmailInput(input) {
//         // Prevent multiple @ symbols on keypress
//         input.addEventListener('keypress', (e) => {
//             const char = e.key;
            
//             // Allow control keys
//             if (e.ctrlKey || e.metaKey || char.length > 1) {
//                 return;
//             }

//             // Check for @ symbol
//             if (char === '@') {
//                 const currentValue = input.value;
//                 const atCount = (currentValue.match(/@/g) || []).length;
                
//                 if (atCount >= 1) {
//                     e.preventDefault();
//                     this.showCharacterBlockedFeedback(input, '@', 'Only one @ symbol allowed');
//                     return false;
//                 }
//             }

//             // Check for consecutive dots
//             if (char === '.') {
//                 const cursorPos = input.selectionStart;
//                 const currentValue = input.value;
//                 const charBefore = currentValue[cursorPos - 1];
                
//                 if (charBefore === '.') {
//                     e.preventDefault();
//                     this.showCharacterBlockedFeedback(input, '..', 'Consecutive dots not allowed');
//                     return false;
//                 }
//             }
//         });

//         // Filter pasted content
//         input.addEventListener('paste', (e) => {
//             e.preventDefault();
//             const pastedText = (e.clipboardData || window.clipboardData).getData('text');
//             const cleaned = this.cleanEmailInput(pastedText);
            
//             // Insert cleaned text at cursor position
//             const start = input.selectionStart;
//             const end = input.selectionEnd;
//             const currentValue = input.value;
//             const newValue = currentValue.substring(0, start) + cleaned + currentValue.substring(end);
            
//             // Check if final value would have multiple @ symbols
//             const finalAtCount = (newValue.match(/@/g) || []).length;
//             if (finalAtCount > 1) {
//                 this.showCharacterBlockedFeedback(input, '@', 'Only one @ symbol allowed');
//                 return;
//             }
            
//             input.value = newValue;
//             input.setSelectionRange(start + cleaned.length, start + cleaned.length);
            
//             // Trigger input event for Livewire
//             input.dispatchEvent(new Event('input', { bubbles: true }));
            
//             if (cleaned !== pastedText) {
//                 this.showPasteCleanedFeedback(input);
//             }
//         });

//         // Real-time validation on input
//         input.addEventListener('input', (e) => {
//             let value = input.value;
//             let cleaned = value;
//             let cursorPos = input.selectionStart;

//             // Remove consecutive dots
//             cleaned = cleaned.replace(/\.{2,}/g, '.');

//             // Limit @ symbols to 1
//             const atPositions = [];
//             for (let i = 0; i < cleaned.length; i++) {
//                 if (cleaned[i] === '@') atPositions.push(i);
//             }
            
//             if (atPositions.length > 1) {
//                 // Keep only the first @
//                 const parts = cleaned.split('@');
//                 cleaned = parts[0] + '@' + parts.slice(1).join('');
//             }

//             if (cleaned !== value) {
//                 input.value = cleaned;
//                 input.setSelectionRange(cursorPos, cursorPos);
//             }
//         });
//     },

//     /**
//      * Initialize validation on a phone input field
//      * @param {HTMLInputElement} input - The phone input element
//      */
//     initPhoneInput(input) {
//         // Validate on keypress
//         input.addEventListener('keypress', (e) => {
//             const char = e.key;
            
//             // Allow control keys
//             if (e.ctrlKey || e.metaKey || char.length > 1) {
//                 return;
//             }

//             const currentValue = input.value;
//             const cursorPos = input.selectionStart;

//             // Check for + symbol
//             if (char === '+') {
//                 const plusCount = (currentValue.match(/\+/g) || []).length;
                
//                 // Only allow + at the beginning
//                 if (plusCount >= 1 || cursorPos !== 0) {
//                     e.preventDefault();
//                     this.showCharacterBlockedFeedback(input, '+', '+ only allowed at start (max 1)');
//                     return false;
//                 }
//             }

//             // Check for dash symbol
//             if (char === '-') {
//                 const dashCount = (currentValue.match(/-/g) || []).length;
                
//                 if (dashCount >= 4) {
//                     e.preventDefault();
//                     this.showCharacterBlockedFeedback(input, '-', 'Maximum 4 dashes allowed');
//                     return false;
//                 }
//             }

//             // Only allow valid phone characters: digits, space, +, -, ()
//             if (!/[\d\s\+\-\(\)]/.test(char)) {
//                 e.preventDefault();
//                 this.showCharacterBlockedFeedback(input, char, 'Only numbers, spaces, +, -, () allowed');
//                 return false;
//             }
//         });

//         // Filter pasted content
//         input.addEventListener('paste', (e) => {
//             e.preventDefault();
//             const pastedText = (e.clipboardData || window.clipboardData).getData('text');
//             const cleaned = this.cleanPhoneInput(pastedText);
            
//             // Insert cleaned text at cursor position
//             const start = input.selectionStart;
//             const end = input.selectionEnd;
//             const currentValue = input.value;
//             const newValue = currentValue.substring(0, start) + cleaned + currentValue.substring(end);
            
//             // Validate the final value
//             const finalPlusCount = (newValue.match(/\+/g) || []).length;
//             const finalDashCount = (newValue.match(/-/g) || []).length;
//             const firstPlusPos = newValue.indexOf('+');
            
//             if (finalPlusCount > 1 || (finalPlusCount === 1 && firstPlusPos !== 0)) {
//                 this.showCharacterBlockedFeedback(input, '+', '+ only allowed at start (max 1)');
//                 return;
//             }
            
//             if (finalDashCount > 4) {
//                 this.showCharacterBlockedFeedback(input, '-', 'Maximum 4 dashes allowed');
//                 return;
//             }
            
//             input.value = newValue;
//             input.setSelectionRange(start + cleaned.length, start + cleaned.length);
            
//             // Trigger input event for Livewire
//             input.dispatchEvent(new Event('input', { bubbles: true }));
            
//             if (cleaned !== pastedText) {
//                 this.showPasteCleanedFeedback(input);
//             }
//         });

//         // Real-time validation on input
//         input.addEventListener('input', (e) => {
//             let value = input.value;
//             let cleaned = value;

//             // Remove invalid characters
//             cleaned = cleaned.replace(/[^\d\s\+\-\(\)]/g, '');

//             // Enforce + only at start
//             const plusMatches = cleaned.match(/\+/g);
//             if (plusMatches && plusMatches.length > 0) {
//                 const firstPlusIndex = cleaned.indexOf('+');
//                 if (firstPlusIndex !== 0) {
//                     cleaned = cleaned.replace(/\+/g, '');
//                 } else {
//                     // Keep only the first +
//                     cleaned = '+' + cleaned.substring(1).replace(/\+/g, '');
//                 }
//             }

//             // Limit dashes to 4
//             const dashMatches = cleaned.match(/-/g);
//             if (dashMatches && dashMatches.length > 4) {
//                 let dashCount = 0;
//                 cleaned = cleaned.split('').filter(char => {
//                     if (char === '-') {
//                         dashCount++;
//                         return dashCount <= 4;
//                     }
//                     return true;
//                 }).join('');
//             }

//             if (cleaned !== value) {
//                 const cursorPos = input.selectionStart;
//                 input.value = cleaned;
//                 input.setSelectionRange(cursorPos, cursorPos);
//             }
//         });
//     },

//     /**
//      * Clean text input by removing blocked characters
//      * @param {string} text - The text to clean
//      * @param {Array} blockedChars - Characters to remove
//      * @returns {string} Cleaned text
//      */
//     cleanTextInput(text, blockedChars = this.blockedChars) {
//         let cleaned = text;
//         blockedChars.forEach(char => {
//             cleaned = cleaned.split(char).join('');
//         });
//         return cleaned;
//     },

//     /**
//      * Clean email input
//      * @param {string} text - The email text to clean
//      * @returns {string} Cleaned email
//      */
//     cleanEmailInput(text) {
//         // Remove consecutive dots
//         let cleaned = text.replace(/\.{2,}/g, '.');
//         return cleaned;
//     },

//     /**
//      * Clean phone input
//      * @param {string} text - The phone text to clean
//      * @returns {string} Cleaned phone
//      */
//     cleanPhoneInput(text) {
//         // Keep only valid phone characters
//         let cleaned = text.replace(/[^\d\s\+\-\(\)]/g, '');
        
//         // Keep only first + and only if at start
//         const firstPlusIndex = cleaned.indexOf('+');
//         if (firstPlusIndex !== -1) {
//             if (firstPlusIndex === 0) {
//                 cleaned = '+' + cleaned.substring(1).replace(/\+/g, '');
//             } else {
//                 cleaned = cleaned.replace(/\+/g, '');
//             }
//         }
        
//         // Limit dashes to 4
//         const dashMatches = cleaned.match(/-/g);
//         if (dashMatches && dashMatches.length > 4) {
//             let dashCount = 0;
//             cleaned = cleaned.split('').filter(char => {
//                 if (char === '-') {
//                     dashCount++;
//                     return dashCount <= 4;
//                 }
//                 return true;
//             }).join('');
//         }
        
//         return cleaned;
//     },

//     /**
//      * Show visual feedback when a character is blocked
//      * @param {HTMLInputElement} input - The input element
//      * @param {string} char - The blocked character
//      * @param {string} message - Custom message
//      */
//     showCharacterBlockedFeedback(input, char, message = null) {
//         // Brief red border flash
//         input.classList.add('border-red-500');
//         setTimeout(() => {
//             input.classList.remove('border-red-500');
//         }, 300);

//         // Optional: show tooltip or small message
//         // This can be customized based on your UI framework
//     },

//     /**
//      * Show feedback when pasted content was cleaned
//      * @param {HTMLInputElement} input - The input element
//      */
//     showPasteCleanedFeedback(input) {
//         // Brief yellow border flash to indicate content was cleaned
//         input.classList.add('border-yellow-500');
//         setTimeout(() => {
//             input.classList.remove('border-yellow-500');
//         }, 500);
//     },

//     /**
//      * Initialize validation on all Manager form inputs within a container
//      * @param {HTMLElement} container - The container element (defaults to document)
//      */
//     initAll(container = document) {
//         // Text inputs with data-manager-text attribute
//         container.querySelectorAll('input[data-manager-text], input[data-validate="text"]').forEach(input => {
//             this.initTextInput(input);
//         });

//         // Email inputs
//         container.querySelectorAll('input[type="email"][data-manager-email], input[data-validate="email"]').forEach(input => {
//             this.initEmailInput(input);
//         });

//         // Phone inputs
//         container.querySelectorAll('input[data-manager-phone], input[data-validate="phone"]').forEach(input => {
//             this.initPhoneInput(input);
//         });
//     }
// };

// // Auto-initialize on page load
// if (typeof window !== 'undefined') {
//     document.addEventListener('DOMContentLoaded', () => {
//         ManagerInputValidator.initAll();
//     });

//     // Re-initialize for dynamically loaded content (Livewire updates)
//     document.addEventListener('livewire:navigated', () => {
//         ManagerInputValidator.initAll();
//     });

//     // For older Livewire versions
//     if (typeof Livewire !== 'undefined') {
//         Livewire.hook('message.processed', () => {
//             ManagerInputValidator.initAll();
//         });
//     }
// }

// // Export for use in modules
// if (typeof module !== 'undefined' && module.exports) {
//     module.exports = ManagerInputValidator;
// }

// // Make available globally
// if (typeof window !== 'undefined') {
//     window.ManagerInputValidator = ManagerInputValidator;
// }
