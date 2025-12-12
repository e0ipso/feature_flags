/**
 * @file
 * UUID generation utility.
 */

/**
 * Generates a simple UUID v4.
 *
 * @return {string} A UUID string conforming to RFC 4122.
 */
function generateUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
    /[xy]/g,
    function replaceUuidChar(c) {
      // UUID v4 generation requires bitwise operations per RFC 4122.
      // eslint-disable-next-line no-bitwise
      const r = (Math.random() * 16) | 0;
      // eslint-disable-next-line no-bitwise
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    },
  );
}

// Make available globally.
if (typeof window !== 'undefined') {
  window.generateUuid = generateUuid;
}

// Export for module usage.
if (typeof module !== 'undefined' && module.exports) {
  module.exports = generateUuid;
}
