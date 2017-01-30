import '../../plugins/uploader';
import initTinymce from '../job/tinymce';
import Tags from '../../libs/tags';
import Dialog from '../../libs/dialog';

/**
 * Cast data from bool to int to properly display radio buttons (0 and 1 instade of true and false).
 *
 * @param data
 * @return {*}
 */
function toInt(data) {
    for (let item in data) {
        if (data.hasOwnProperty(item)) {
            if (typeof(data[item]) == 'boolean') {
                data[item] = +data[item];
            }

            if (typeof(data[item]) == 'object') {
                data[item] = toInt(data[item]);
            }
        }
    }

    return data;
}

var map;

new Vue({
    el: '.submit-form',
    delimiters: ['${', '}'],
    data: toInt(data),
    mounted: function () {
        initTinymce();

        if (typeof google !== 'undefined') {
            google.maps.event.addDomListener(window, 'load', initialize);
        }

        new Tags({
            onSelect: (value) => {
                this.tags.push({name: value, pivot: {priority: 1}});
                let pluck = this.tags.map((item) => item.name);

                $.get($('#tag').data('suggestions-url'), {t: pluck}, result => {
                    this.suggestions = result;
                });
            }
        });

        $('#tags-container').each(function () {
            $(this).sortable();
        });
    },
    methods: {
        /**
         * Add tag after clicking on suggestion tag.
         *
         * @param {String} value
         */
        addTag: function (value) {
            this.tags.push({name: value, pivot: {priority: 1}});
        },
        removeTag: function (index) {
            this.tags.splice(index, 1);
        },
        isInvalid: function (fields) {
            return Object.keys(this.errors).findIndex(element => fields.indexOf(element) > -1) > -1;
        },
        charCounter: function (item, limit) {
            let model = item.split('.').reduce((o, i) => o[i], this);

            return limit - String(model !== null ? model : '').length;
        },
        toggleBenefit: function (item) {
            let index = this.benefits.indexOf(item);

            if (index === -1) {
                this.benefits.push(item);
            } else {
                this.benefits.splice(index, 1);
            }
        },
        addBenefit: function (e) {
            if (e.target.value.trim()) {
                this.benefits.push(e.target.value);
            }

            e.target.value = '';
        },
        removeBenefit: function (benefit) {
            this.benefits.splice(this.benefits.indexOf(benefit), 1);
        },
        updateBenefit: function () {
            //
        },
        addFirm: function () {
            let dialog = new Dialog({
                title: 'Dodanie nowej firmy',
                message: 'Czy na pewno chcesz dodać nową firme i przypisać ją do tego ogłoszenia?',
                buttons: [{
                    label: 'Anuluj',
                    attr: {
                        'class': 'btn btn-default',
                        'type': 'button',
                        'data-dismiss': 'modal'
                    }
                }, {
                    label: 'Tak',
                    attr: {
                        'class': 'btn btn-primary'
                    },
                    onClick: () => {
                        this.firm = {
                            'id': null,
                            'headline': '',
                            'logo': null,
                            'description': null,
                            'website': null,
                            'is_private': +false,
                            'is_agency': false,
                            'employees': null,
                            'founded': null
                        };

                        this.benefits = [];
                        dialog.close();
                    }
                }]
            });

            dialog.show();
        },
        selectFirm: function (firmId) {
            let index = this.firms.findIndex(element => element.id == firmId);

            this.firm = this.firms[index];
            this.firm.is_private = +false; // must be the number - not bool

            this.benefits = this.firm.benefits;
        }
    },
    computed: {
        deadlineDate: function () {
            let value = parseInt(this.job.deadline);

            if (value > 0) {
                let date = new Date();
                date.setDate(date.getDate() + value);

                return date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
            }
            else {
                return '--';
            }
        }
    },
    watch: {
        'job.enable_apply': function (flag) {
            if (Boolean(parseInt(flag))) {
                tinymce.get('recruitment').hide();

                $('#recruitment').attr('disabled', 'disabled').hide();
            } else {
                tinymce.get('recruitment').show();

                $('#recruitment').removeAttr('disabled');
            }
        },
        'firm.is_private': function (flag) {
            if (!Boolean(parseInt(flag))) {
                google.maps.event.trigger(map, 'resize');
            }
        }
    }
});


$(() => {
    'use strict';

    let navigation = $('#form-navigation');
    let fixed = $('#form-navbar-fixed');

    $('#form-navigation-container')
        .html(navigation.html())
        .on('click', ':submit', () => $('.submit-form').submit())
        .on('click', 'button[data-submit-state]', e => $(e.currentTarget).attr('disabled', 'disabled').text($(e.currentTarget).data('submit-state')));

    if (navigation.length) {
        $(window).scroll(() => {
            let bottom = $(window).scrollTop() + $(window).height();

            if (bottom > navigation.offset().top) {
                fixed.fadeOut();
            }
            else {
                fixed.show();
            }
        }).trigger('scroll');
    }

    /**
     * Save and exit button
     */
    $('.btn-save').on('click', () => {
        $('input[name="done"]').val(1);
    });

    $('.jumbotron .btn-close').click(() => {
        $('.jumbotron .close').click();
    });

    $('.submit-form').on('focus', ':input', e => {
        let $this = $(e.currentTarget);
        let offset = $this.offset().top;
        let name = $this.attr('name');

        $('.sidebar-hint').hide();

        if (typeof name !== 'undefined') {
            name = name.replace('[', '').replace(']', '');

            $('#hint-' + name).fadeIn();
            offset -= $('aside').offset().top;

            $('#hint-container').css('top', offset);
        }
    });

    $.uploader({
        input: 'logo',
        onChanged: function(data) {
            $('#firm-form').find('input[name="logo"]').val(data.name);
        },
        onDeleted: function() {
            $('#firm-form').find('input[name="logo"]').val('');
        }
    });

    /**
     * Ability to create new firm and assign it to the offer
     */
    // $('#box-edit-firm').find('input[name="name"]').one('keyup', () => {
    //     if ($('#firm-id').val() === '') {
    //         return true;
    //     }
    //
    //     $('#modal-firm').modal('show').find('.btn-primary').one('click', () => {
    //         $('#btn-add-firm').click();
    //
    //         return false;
    //     });
    // });

    /**
     * Ability to assign different firm to this job offer
     */
    // $('.btn-firm').click(e => {
    //     let self = $(e.currentTarget);
    //
    //     $.get(self.attr('href'), (html) => {
    //         $('#box-edit-firm').replaceWith(html);
    //         initialize();
    //
    //         $('.btn-firm').not(self).removeClass('btn-primary').addClass('btn-default');
    //         self.addClass('btn-primary').removeClass('btn-default');
    //
    //         tinymce.EditorManager.editors = [];
    //         initTinymce();
    //     });
    //
    //     return false;
    // });


});

function initialize() {
    'use strict';

    let mapOptions =
    {
        zoom: 6,
        center: new google.maps.LatLng(51.919438, 19.14513599999998),
        mapTypeId: google.maps.MapTypeId.ROADMAP
    };

    let geocoder = new google.maps.Geocoder();
    map = new google.maps.Map(document.getElementById("map"), mapOptions);
    let marker;

    let geocodeResult = function (results, status) {
        if (status === google.maps.GeocoderStatus.OK) {
            map.setCenter(results[0].geometry.location);

            if (marker) {
                marker.setMap(null);
            }

            marker = new google.maps.Marker({
                map: map,
                position: results[0].geometry.location
            });

            map.setZoom(16);
            $('#address').val(results[0].formatted_address);

            let country = '', city = '', street = '', postcode = '';
            let components = results[0].address_components;

            for (let item in components) {
                if (components.hasOwnProperty(item)) {
                    let component = components[item];

                    if (!country && component.types.indexOf('country') > -1) {
                        country = component.long_name;
                    }
                    if (!postcode && component.types.indexOf('postal_code') > -1) {
                        postcode = component.long_name;
                    }
                    if (!city && component.types.indexOf('locality') > -1) {
                        city = component.long_name;
                    }
                    if (!postcode && component.types.indexOf('route') > -1) {
                        street = component.long_name;
                    }
                }
            }

            $(':hidden[name=country]').val(country);
            $(':hidden[name=city]').val(city);
            $(':hidden[name=street]').val(street);
            $(':hidden[name=postcode]').val(postcode);
            $('#longitude').val(results[0].geometry.location.lng());
            $('#latitude').val(results[0].geometry.location.lat());
        }
    };

    let geocode = function (address) {
        geocoder.geocode({'address': address}, geocodeResult);
    };

    let reverseGeocode = function (coordinates) {
        geocoder.geocode({'latLng': coordinates}, geocodeResult);
    };

    if (!isNaN(parseFloat($('#latitude').val())) && !isNaN(parseFloat($('#longitude').val()))) {
        let coordinates = new google.maps.LatLng(parseFloat($('#latitude').val()), parseFloat($('#longitude').val()));

        marker = new google.maps.Marker({
            map: map,
            position: coordinates
        });

        map.setCenter(coordinates);
    }

    let onAddressChange = function () {
        let val = $.trim($('#address').val());

        if (val.length) {
            geocode(val);
        }
        else {
            $('#longitude, #latitude').val(0);
            $(':hidden[name=country], :hidden[name=city], :hidden[name=street], :hidden[name=postcode]').val('');

            marker.setMap(null);
        }
    };

    $('#address').keypress(function (e) {
        let code = e.keyCode || e.which;

        if (code === 13) {
            onAddressChange();
            return false;
        }
    });

    google.maps.event.addListener(map, 'click', (e) => {
        reverseGeocode(e.latLng);
    });

    return map;
}
