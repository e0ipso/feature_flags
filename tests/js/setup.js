/**
 * @file
 * Jest setup file for Feature Flags tests.
 */

// Mock localStorage
const localStorageMock = (() => {
  let store = {};

  return {
    getItem(key) {
      return store[key] || null;
    },
    setItem(key, value) {
      store[key] = String(value);
    },
    removeItem(key) {
      delete store[key];
    },
    clear() {
      store = {};
    },
  };
})();

global.localStorage = localStorageMock;

// Mock global Drupal object
global.Drupal = {
  t: str => str,
  featureFlags: {},
};

// Mock drupalSettings
global.drupalSettings = {
  featureFlags: {
    flags: {},
    settings: {},
  },
};

// Mock document.dispatchEvent for CustomEvent
if (typeof document === 'undefined') {
  global.document = {
    dispatchEvent: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  };
} else {
  // Make sure document methods are jest functions
  if (!document.dispatchEvent.mockClear) {
    document.dispatchEvent = jest.fn(document.dispatchEvent);
  }
  if (!document.addEventListener.mockClear) {
    document.addEventListener = jest.fn(document.addEventListener);
  }
  if (!document.removeEventListener.mockClear) {
    document.removeEventListener = jest.fn(document.removeEventListener);
  }
}

// Reset mocks before each test
beforeEach(() => {
  localStorage.clear();
  global.drupalSettings = {
    featureFlags: {
      flags: {},
      settings: {},
    },
  };
  if (document.dispatchEvent.mockClear) {
    document.dispatchEvent.mockClear();
  }
});
