!function(e){"use strict";e.fn.tag=function(){function t(){e(':hidden[name="tag[]"]').remove(),e("ul.tag-clouds li",n).each(function(){e("<input>",{type:"hidden",name:"tag[]"}).val(e(this).text()).insertAfter(i)});var t=r-e(".tag-clouds").outerWidth();i.width(Math.max(100,t)),100>t?(t=0>t?Math.abs(t-100):100-t,e(".tag-clouds",n).css("left",-t),i.css("left",-t),n.width(r)):(i.css("left",0),e(".tag-clouds",n).css("left",0))}function a(t){return e.trim(t.toLowerCase().replace(/[^a-ząęśżźćółń0-9\-\.#\+\s]/gi,""))}var l,i=e(this),o=e('<ol class="tag-suggestions"></ol>'),s=-1;i.removeAttr("name"),i.wrap('<div class="form-control tag-editor"></div>');var n=e(".tag-editor"),r=n.width();o.css({width:n.outerWidth(),left:n.position().left,top:n.position().top+n.outerHeight()}),i.attr("autocomplete","off"),o.insertAfter(n),n.prepend('<ul class="tag-clouds"></ul>'),""!==e.trim(i.val())&&e.each(i.val().split(","),function(t,a){n.children("ul").append('<li><a class="remove">'+e.trim(a)+"</a></li>")}),t(),i.val(""),e(".tag-clouds",n).delegate("a.remove","click",function(){e(this).parent().remove(),t()});var d=function(t){var a=e("li:visible",o).length;a>0&&(t>=a?t=0:0>t&&(t=a-1),s=t,e("li:visible",o).removeClass("hover"),e("li:visible:eq("+s+")",o).addClass("hover"))},u=function(r,d){e("li",o).removeClass("hover").show(),s=-1,r=a(r),""!==r&&(n.children("ul").append('<li><a class="remove">'+r+"</a></li>"),t(),n.find("li").length>5?(e("#alert").modal("show"),e(".modal-body").text("Maksymalna ilość tagów to 5")):e.ajax({type:"GET",url:baseUrl+"/Tag/Validate",data:{t:r},dataType:"json",crossDomain:!0,xhrFields:{withCredentials:!0},error:function(a){"undefined"!=typeof a.responseJSON.t&&(e("#alert").modal("show"),e(".modal-body").text(a.responseJSON.t[0]),e("ul.tag-clouds li:last",n).remove(),t())}})),clearTimeout(l),o.hide(),i.val(""),d!==!1&&i.focus()};o.delegate("li","click",function(){u(e(this).children("span").text())}).delegate("li","mouseenter mouseleave",function(t){"mouseenter"===t.type?e(this).addClass("hover"):e(this).removeClass("hover")}),i.keydown(function(e){var t=e.keyCode||window.event.keyCode;27===t?(i.val(""),o.hide()):13===t&&""!==i.val()&&e.preventDefault()}).keyup(function(a){var r=a.keyCode||window.event.keyCode;if(40===r)d(s+1);else if(38===r)d(s-1);else if(13===r)""!==e("li.hover",o).text()?(u(e("li.hover span",o).text()),a.preventDefault()):""!==i.val()&&(u(i.val()),a.preventDefault());else if(8===r&&0===i.val().length){var c=e("ul.tag-clouds li:last",n).text();c&&(e("ul.tag-clouds li:last",n).remove(),t(),i.val(" "+c))}else if(32===r||188===r)u(i.val());else{var v=e.trim(e(this).val().toLowerCase());clearTimeout(l),v.length?l=setTimeout(function(){e.ajax({type:"GET",url:baseUrl+"/Tag/Prompt",data:{q:v},crossDomain:!0,xhrFields:{withCredentials:!0},success:function(t){""!==e.trim(t)?o.html(t).css("top",n.position().top+n.outerHeight()).show():o.hide()}})},200):o.hide()}}).blur(function(){o.is(":hidden")&&""!==i.val()&&u(i.val(),!1)}),e(document).bind("click",function(t){var a=e(t.target);a.is(i)||(""!==i.val()&&u(i.val(),!1),o.hide())})}}(jQuery);
//# sourceMappingURL=tags.js.map