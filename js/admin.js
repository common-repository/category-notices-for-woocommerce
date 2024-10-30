(function($) {
  $(window).ready(function() {
    // Validation
    $("#submit").click(function (e) {
      let empty_notices = 0;
      let empty_categories = 0;
      $(".category-notice").each(function () {
        if (!$(this).val()) {
          empty_notices++;
        }
      });
      $(".categories").each(function () {
        if (!$(this).val()) {
          empty_categories++;
        }
      });
      if (empty_categories > 0) {
        e.preventDefault();
        alert("You are missing one or more category fields.");
      } else if (empty_notices > 0) {
        e.preventDefault();
        alert("You are missing one or more notice fields.");
      }
    });
  });
})(jQuery);
