		      (function () {
  $('#scrollup').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
  });
  $('#scrolldown').on({
    'mousedown touchstart': function() {
      $(".lista_prod").animate({
        scrollTop:  $(".lista_prod")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_prod").stop(true);
    }
});
 $('.rightArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});
 $('.leftArrow').on({
    'mousedown touchstart': function() {
      var leftPos = $('.content_cat').scrollLeft();
  $(".content_cat").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".content_cat").stop(true);
    }
});
 $('.rightArrowAmanate').on({
    'mousedown touchstart': function() {
      var leftPos = $('.bamanate').scrollLeft();
  $(".bamanate").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});
 $('.leftArrowAmanate').on({
    'mousedown touchstart': function() {
      var leftPos = $('.bamanate').scrollLeft();
  $(".bamanate").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});
 $('.rightArrowRelistare').on({
    'mousedown touchstart': function() {
      var leftPos = $('.brelistare').scrollLeft();
  $(".brelistare").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});
 $('.leftArrowRelistare').on({
    'mousedown touchstart': function() {
      var leftPos = $('.brelistare').scrollLeft();
  $(".brelistare").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});
 $('.rightArrowClienti').on({
    'mousedown touchstart': function() {
      var leftPos = $('.clienti').scrollLeft();
  $(".clienti").animate({scrollLeft: leftPos + 200}, 800);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
});
 $('.leftArrowClienti').on({
    'mousedown touchstart': function() {
      var leftPos = $('.clienti').scrollLeft();
  $(".clienti").animate({scrollLeft: leftPos - 200}, 800);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
});
    $('#scrollup2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
  });
  $('#scrolldown2').on({
    'mousedown touchstart': function() {
      $(".lista_nomencl").animate({
        scrollTop:  $(".lista_nomencl")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".lista_nomencl").stop(true);
    }
});
    $('#scrollup3').on({
    'mousedown touchstart': function() {
      $(".bamanate").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
  });
  $('#scrolldown3').on({
    'mousedown touchstart': function() {
      $(".bamanate").animate({
        scrollTop:  $(".bamanate")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".bamanate").stop(true);
    }
});
    $('#scrollup4').on({
    'mousedown touchstart': function() {
      $(".brelistare").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
  });
  $('#scrolldown4').on({
    'mousedown touchstart': function() {
      $(".brelistare").animate({
        scrollTop:  $(".brelistare")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".brelistare").stop(true);
    }
});
    $('#scrollup5').on({
    'mousedown touchstart': function() {
      $(".clienti").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".clienti").stop(true);
    }
  });
  $('#scrolldown5').on({
    'mousedown touchstart': function() {
      $(".clienti").animate({
        scrollTop:  $(".clienti")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".categrii").stop(true);
    }
});
    $('#scrollup6').on({
    'mousedown touchstart': function() {
      $(".categrii").animate({scrollTop:  0}, 500);
    },
    'mouseup touchend': function() {
      $(".categrii").stop(true);
    }
  });
  $('#scrolldown6').on({
    'mousedown touchstart': function() {
      $(".categrii").animate({
        scrollTop:  $(".categrii")[0].scrollHeight
      }, 1000);
    },
    'mouseup touchend': function() {
      $(".categrii").stop(true);
    }
});
})();
