document.addEventListener("DOMContentLoaded", function () {
  function nav() {
    var ul = document.querySelector("nav-navbar + ul");
    ul.id = "js-menu";
    var mainNav = document.querySelector("#js-menu");
    var navBarToggle = document.querySelector("#js-navbar-toggle");
    var firstElement = mainNav.querySelectorAll("a, button")[0];

    const open = function () {
      mainNav.style.diplay = "block";

      setTimeout(function () {
        mainNav.classList.add("active");
        mainNav.setAttribute("aria-hidden", "false");
        navBarToggle.classList.add("active");

        if (firstElement) firstElement.focus();

        window.addEventListener("keydown", escapeEvent);
        window.addEventListener("keyup", tabEvent);
      }, 100);
    };

    const close = function () {
      mainNav.classList.remove("active");
      mainNav.setAttribute("aria-hidden", "true");
      navBarToggle.classList.remove("active");

      window.removeEventListener("keydown", escapeEvent);
      window.removeEventListener("keyup", tabEvent);

      setTimeout(function () {
        mainNav.style.diplay = "none";
      }, 600);
    };

    const tabEvent = function (event) {
      if (event.key === "Tab" || event.keyCode == 9) {
        if (mainNav && !mainNav.contains(event.srcElement)) {
          close();
        }
      }
    };

    const escapeEvent = function (event) {
      if (event.keyCode == 27 || event.key === "Esc") {
        close();
        navBarToggle.focus();
      }
    };

    navBarToggle.addEventListener("click", function () {
      var isHidden = mainNav.getAttribute("aria-hidden");

      isHidden === "true" ? open() : close();
    });
  }

  nav();
});
