(function ($) {
    if (!$) return alert('jQuery is not found');
    
    
    var cookie = {
        set: function (name, value, stringifyObject) {
            if (value && stringifyObject) value = JSON.stringify(value);

            var date = new Date();
            date.setTime(date.getTime() + 24 * 60 * 60 * 1000);

            document.cookie = name + '=' + (value || '')  + '; expires=' + date.toUTCString() + '; path=/';
        },
        remove: function (name) {
            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        }
    };
    
    
    $(function () {
        var WIDGET_DOM_ID = 'dd-widget';
        // Класс, который будет навешиваться на узел монтирования виджета при появлении виджета
        var WIDGET_VISIBLE_CSS_CLASS = 'widget-visible';
        
        
        // Селект со списком методов доставки
        var $shipSelect = $('.tsvshiplist select');
        // Селект со списком методов оплаты
        var $paySelect = $('.tsvpaylist select');
        // Кнопка отправки формы
        var $formSubmit = $('form input:submit');
        
        
        var widget;
        
        
        // Добавление на страницу DOM-узла для монтирования виджета
        $('body').append('<div id="' + WIDGET_DOM_ID + '"></div>');
        
        
        var $widget = $('#' + WIDGET_DOM_ID);
        
        
        // Определяет, является ли выбранный в настоящий момент способ доставки доставкой DDelivery
        function currentShipIsDDelivery () {
            return ($shipSelect.find('option:selected').text().search(/DDelivery/) !== -1);
        }
        
        // Устанавливает способ оплаты DDelivery (если он есть) и отключает все остальные способы оплаты
        function setPayDDelivery () {
            var ddeliveryPayExists = false,
                notDDeliveryPayMethods = [];
            
            $paySelect.find('option').each(function () {
                if ($(this).text().search(/DDelivery/) !== -1) {
                    ddeliveryPayExists = true;
                    
                    // Разблокировка варианта DDelivery, если он был заблокирован
                    $(this).prop('disabled', false);
                    // Установка варианта DDelivery
                    $paySelect.val($(this).val()).trigger('change');
                } else {
                    notDDeliveryPayMethods.push($(this));
                }
            });
            
            // Если способ оплаты DDelivery есть в списке
            if (ddeliveryPayExists) {
                // Все остальные способы оплаты блокируются
                notDDeliveryPayMethods.forEach(function ($option) {
                    $option.prop('disabled', true);
                });
            }
        }
        
        // Отключает способ оплаты DDelivery, остальные способы делает активными
        function disablePayDDelivery () {
            $paySelect.find('option').each(function () {
                var isDDelivery = ($(this).text().search(/DDelivery/) !== -1);
                
                $(this).prop('disabled', isDDelivery);
                
                // Сброс текущего выбранного варианта, если в списке есть способ DDelivery
                if (isDDelivery) $paySelect.val('').trigger('change');
            });
        }
        
        // Инициализирует виджет
        function widgetInit () {
            $widget.addClass(WIDGET_VISIBLE_CSS_CLASS);
            $formSubmit.prop('disabled', true);
            
            var widgetData;
            
            widget = new DDeliveryWidgetCart(WIDGET_DOM_ID, {
                lang:      DDELIVERY.LANG,
                apiScript: DDELIVERY.API_SCRIPT,
                weight:    DDELIVERY.WEIGHT,
                products:  DDELIVERY.PRODUCTS,
                discount:  DDELIVERY.DISCOUNT,
                mod:       'modx_evolution'
            });
            
            widget.on('change', function (data) {
                widgetData = data;
            });
            
            widget.on('afterSubmit', function (response) {
                if (response.status === 'ok') {
                    lockShipSelect();
                    
                    setDeliveryPrice(
                        (widgetData.delivery.type == 1)
                            ? widgetData.delivery.point.price_delivery
                            : widgetData.delivery.total_price
                    );
                    
                    // Сохранение данных виджета и заказа в Cookies
                    cookie.set('DDWidgetData', widgetData, true);
                    cookie.set('DDOrderData', {
                        id: response.id,
                        confirmed: response.confirmed
                    }, true);
                    
                    // Для пересчета суммы "К оплате"
                    $shipSelect.trigger('change', { widget: false });
                } else {
                    clearShippingSelect();
                    console.error(response.message);
                }
                
                widgetDestruct();
            });
            
            widget.on('error', function (e) { console.error(e); });
        }
        
        // Убирает виджет со страницы
        function widgetDestruct () {
            $widget.removeClass(WIDGET_VISIBLE_CSS_CLASS);
            $formSubmit.prop('disabled', false);
            
            if (widget) widget.destruct();
        }
        
        // Блокирует возможность смены метода доставки в селекте
        function lockShipSelect () {
            $shipSelect.find('option:not(:selected)').remove();
        }
        
        // Сбрасывает выбранный в селекте метод доставки
        function clearShippingSelect () {
            $shipSelect.val($shipSelect.find('option').first().attr('value')).trigger('change');
        }
        
        // Устанавливает стоимость доставки DDelivery в селекте "Метод доставки"
        function setDeliveryPrice (price) {
            $shipSelect.find('option').each(function () {
                if ($(this).text().search(/DDelivery/) !== -1)
                    $(this).text('DDelivery (' + price + ' ' + DDELIVERY.CURRENCY + ')');
            });
        }
        
        // Удаляет данные виджета и заказа DDelivery из Cookies
        function clearDDeliveryCookies () {
            cookie.remove('DDWidgetData');
            cookie.remove('DDOrderData');
        }
        
        
        // Смена выбранного способа доставки на чекауте
        $shipSelect.on('change', function (e, data) {
            if (currentShipIsDDelivery()) {
                setPayDDelivery();
                
                // Инициализация виджета только если не было передано { widget: false }
                if (!data || data.widget !== false) widgetInit();
            } else {
                disablePayDDelivery();
                widgetDestruct();
                clearDDeliveryCookies();
            }
        });
        
        // Закрытие виджета по нажатию ESC
        $(window).on('keydown', function (e) {
            if (e.keyCode === 27) {
                widgetDestruct();
                clearShippingSelect();
            }
        });
        
        // Закрытие виджета по клику за пределами виджета
        $widget.on('click', function () {
            widgetDestruct();
            clearShippingSelect();
        });
        
        
        clearDDeliveryCookies();
        clearShippingSelect();
    });
})
((typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined') ? $ : null);