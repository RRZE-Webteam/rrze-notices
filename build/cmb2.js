/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/cmb2/script.js":
/*!****************************!*\
  !*** ./src/cmb2/script.js ***!
  \****************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var jQuery = __webpack_require__(/*! jquery */ "jquery");


jQuery(document).ready(function ($) {
  let visibilityTypeInput = $("input[name='notice_visibility']");
  triggerVisibilityTypeField();
  visibilityTypeInput.on("change", function () {
    triggerVisibilityTypeField();
  });
  function triggerVisibilityTypeField() {
    var visibilityType = $("input[name='notice_visibility']:checked").val();
    if (typeof visibilityType == "undefined") {
      $("div.notice_visibility_roles").hide();
    } else if (visibilityType === "all") {
      $("div.notice_visibility_roles").slideUp();
    } else if (visibilityType === "roles") {
      $("div.notice_visibility_roles").slideDown();
    }
  }
  let repeatCheck = $("input#event_repeat");
  let repeatIntervalSelect = $("select#event_repeat_interval");
  let repeatMonthlyTypeInput = $("input[name='event_repeat_monthly_type']");
  if (repeatCheck.length > 0) {
    triggerRepeatFields();
  }
  repeatCheck.on("change", function () {
    triggerRepeatFields();
  });
  repeatIntervalSelect.on("change", function () {
    triggerIntervalFields();
  });
  repeatMonthlyTypeInput.on("change", function () {
    triggerMonthlyTypeFields();
  });
  function triggerRepeatFields() {
    if (repeatCheck.is(":checked")) {
      $("div.event-repeat").slideDown();
      triggerIntervalFields();
    } else {
      $("div.event-repeat").slideUp();
    }
  }
  function triggerIntervalFields() {
    var repeatInterval = $("option:selected", repeatIntervalSelect).val();
    if (repeatInterval === "week") {
      $("div.event-repeat-weekly").slideDown();
      $("div.event-repeat-monthly").slideUp();
    } else if (repeatInterval === "month") {
      $("div.event-repeat-monthly").slideDown();
      $("div.event-repeat-weekly").slideUp();
      triggerMonthlyTypeFields();
    }
  }
  function triggerMonthlyTypeFields() {
    var repeatMonthlyType = $("input[name='event_repeat_monthly_type']:checked").val();
    if (typeof repeatMonthlyType == "undefined") {
      $("div.event-repeat-monthly-date").hide();
      $("div.event-repeat-monthly-dow").hide();
    } else if (repeatMonthlyType === "dow") {
      $("div.event-repeat-monthly-dow").slideDown();
      $("div.event-repeat-monthly-date").slideUp();
    } else if (repeatMonthlyType === "date") {
      $("div.event-repeat-monthly-date").slideDown();
      $("div.event-repeat-monthly-dow").slideUp();
    }
  }
});

/***/ }),

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["jQuery"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!***************************!*\
  !*** ./src/cmb2/index.js ***!
  \***************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _script_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./script.js */ "./src/cmb2/script.js");
/* harmony import */ var _script_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_script_js__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Plugin: Admin Dependencies
 */

})();

/******/ })()
;
//# sourceMappingURL=cmb2.js.map