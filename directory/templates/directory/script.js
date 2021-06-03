var SubordinateTree = function(arParams) {
    this.selectors = arParams.selectors;
    this.usersContainer = arParams.selectors.usersContainer;
    this.viewSubordinate = arParams.selectors.viewSubordinate;
    this.visibleTree = arParams.selectors.visibleTree;
    this.messages = arParams.messages;
    this.init();
};

SubordinateTree.prototype = {
    init: function () {
        this.registerHandlers();
    },

    registerHandlers: function() {
        $(this.usersContainer).on('click', this.viewSubordinate, this.showSubordinate);
    },

    showSubordinate: function(e) {
        e.preventDefault();

        let errorsContainer = $('[data-errors-container]');
        let itemId =  $(this).data('id-element') ?? '';
        let autoCardId = $(this).data('auto-card') ?? '';
        let headDepartment =  $(this).data('head-dep') ?? '';
        let counterNum =  $(this).data('counter') ?? 0;
        let wrapper = $('[data-id-element="' + itemId + '"]').next(this.visibleTree);

        if (url = $(e.target).data('href')) {
            window.open(url, '_self');
        }

        wrapper.toggleClass("hidden");

        let accordion = $('[data-accordion="' + itemId + '"]');
        accordion.find('.accordion-header__icon').toggleClass("hidden");

        BX.ajax.runComponentAction("beluga:directory",
            "getAllSubordinates",
            {
                mode: "class",
                dataType: "json",
                data: {headHrId: itemId, autoCardId: autoCardId, headDepartment: headDepartment, counterNum: counterNum}
            }).then(function(response){
            if(response.status == 'success') {
                if(Object.keys(response.data).length != 0) {
                    let template = '';
                    $.each(response.data,function(id,data){
                        const WIDTH = 100;
                        const MARGIN = 3;
                        let level = MARGIN * data['COUNTER'];
                        let widthValue = WIDTH - level;
                        let fullName = '';
                        let curDepartment = '';
                        let companyName = '';
                        let accordionArrow = '';
                        let headDepartment = '';

                        // Валидация полей
                        data['FULL_NAME'] ? fullName = '<a title="' + data['FULL_NAME'] + '" href="#" data-href="' + data['DETAIL_URL_PERSONAL'] + '" class="directory-text__name">' + data['FULL_NAME'] + '</a>\n' : fullName = '\n';
                        data['WORK_POSITION'] = data['WORK_POSITION'] ?? '';
                        data['AUTO_CARD'] = data['AUTO_CARD'] ?? '';
                        data['HEAD_DEPARTMENT'] ? headDepartment = '<span class="directory-text__department">' + data['HEAD_DEPARTMENT'] + '</span>\n' : headDepartment = '\n';
                        data['MANAGER_DEPARTMENT'] ? curDepartment = '<span class="directory-text__department-to">' + data['MANAGER_DEPARTMENT'] + '</span>\n' : curDepartment = '\n';
                        data['COMPANY_NAME'] ? companyName = '<span class="directory-text__company">' + data['COMPANY_NAME'] + '</span>\n' : companyName = '\n';
                        data['IS_MANAGER'] ? accordionArrow = '<div class="accordion-header__icon accordion-header__icon_down"></div><div class="accordion-header__icon accordion-header__icon_up hidden"></div>\n' : accordionArrow = '\n';

                        template +=	'<div class="accordion-header" data-counter="' + data['COUNTER'] + '" data-head-dep="' + data['MANAGER_DEPARTMENT'] + '" data-id-element="' + data['ID_ST'] + '" data-auto-card="' + data['AUTO_CARD'] + '" data-action="viewSubordinate">\n' +
                            '<div data-accordion="'+ data['ID_ST'] +'" style="width: ' + widthValue + '%;" class="directory-item tree level-child">\n'+
                            '<div class="directory-info">' +
                            '<a href="#" title="' + data['FULL_NAME'] + '" class="directory-img level-child__img">\n' +
                            '<img data-href="' + data['DETAIL_URL_PERSONAL'] + '" src="' + data['PERSONAL_PHOTO'] + '" alt="' + data['FULL_NAME'] + '" title="' + data['FULL_NAME'] + '" class="directory-img__item">\n' +
                            '</a>\n' +
                            '<div class="directory-text">\n' +
                            '<div class="directory-text--top">\n' +
                            fullName +
                            headDepartment +
                            curDepartment +
                            '</div>\n' +
                            '<div class="directory-text--bottom">\n' +
                            '<a title="' + data['WORK_POSITION'] + '" data-href="' + data['DETAIL_URL_PERSONAL'] + '" href="#" class="directory-text__position">' + data['WORK_POSITION'] + '</a>\n' +
                            companyName +
                            '</div>\n' +
                            '</div>\n' +
                            '</div>\n' +
                            accordionArrow +
                            '</div>\n' +
                            '</div>\n' +
                            '<div class="border-none hidden">\n' +
                            '<div class="accordion accordion-child">\n' +
                            '<div class="accordion-item" data-visible="tree">\n' +
                            '</div>\n' +
                            '</div>\n' +
                            '</div>\n';
                    });
                    wrapper.html(template);
                }
            }
        }).catch(function (response) {
            $.each(response.errors, function() {
                errorsContainer.html('<div class="ui-alert ui-alert-danger"><span class="ui-alert-message">' + this.message + '</span></div>');
            });
        });
    },
}
