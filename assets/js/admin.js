
const ctx = document.getElementById('curve_chart').getContext('2d');
const myChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: data_table.dates,
        datasets: [{
            label: 'Total orders',
            data: data_table.order_count,
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderColor: 'rgba(255, 99, 132, 0.2)',
        },{
            label: 'Orders with >1 items',
            data: data_table.multiple_item_order_count,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 0.2)',
        }]
    },
    options: {
        aspectRatio: 4
    }
});

jQuery(document).ready(function($) {
    $('.ajax').click(function(){
        var date = $(this).data('date');
        var data = {
            'action': 'recommat_admin_report_order_info_ajaxurl',
            'date': date
        };
        $.ajax( {
            url: wpApiSettings.root + 'recommat/v1/admin/order/',
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
            },
            data: data,
        }).done( function ( response ) {
            for (key in response) {
                var order_total = response[key]['order_total'];
                var items = response[key]['items'];
                var markup = '<tr><td></td><td colspan="6">- <a target="_blank" href="'+admin_url+'post.php?action=edit&post='+key+'">Order '+key+'</a></td></tr>';
                for (i in items) {
                    markup = markup+'<tr><td></td><td colspan="5">-- <a target="_blank" href="'+admin_url+'post.php?action=edit&post='+items[i]['id']+'">'+items[i]['name']+'</a> (x'+items[i]['qty']+')</td><td class="text-end">$'+items[i]['price']+'</td></tr>';
                }
                markup = markup+'<tr><td></td><td colspan="5"></td><td class="text-end">$'+order_total+'</td></tr>';
                $('#d'+date).after(markup);
            };
        });
        return false;
    });
})