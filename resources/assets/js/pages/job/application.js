import initTinymce from '../../libs/tinymce';
import Dialog from '../../libs/dialog';

$(function() {
    initTinymce();

    let form = $('<form />', {method: 'post', 'action': $('#uploader').data('upload-url')});
    let input = $('<input />', {type: 'file', name: 'cv', id: 'input-file', style: 'visibility: hidden; height: 0'}).appendTo(form);

    form.appendTo('body');

    $('#uploader').click(function() {
        $('#input-file').click();
    });

    $('#input-file').change(function() {
        let formData = new FormData(form[0]);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: (data) => {
                $('#uploader').text(data.name);
                $(':hidden[name="cv"]').val(data.filename);
            },
            error: function (err) {
                Dialog.alert({message: err.responseJSON.cv[0]}).show();
            }
        }, 'json');
    });
});
