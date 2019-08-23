jQuery(document).ready(function($) {

  let multipliers_table = $('#multipliers_table').DataTable({
      "paging":   false,
      "order": [[ 0, "asc" ]],
      "aoColumns": [
        { "orderSequence": [ "asc", "desc" ] },
        { "orderSequence": [ "desc", "asc" ] },
        { "orderSequence": [ "desc", "asc" ] },
        { "orderSequence": [ "desc", "asc" ] },
        { "orderSequence": [ "desc", "asc" ] },
        { "orderSequence": [ "asc", "desc" ] },
      ]
  });

  multipliers_table.columns( '.select-filter' ).every( function () {
    var that = this;
    // Create the select list and search operation
    var select = $('<select />')
      .appendTo(
        this.header()
      )
      .on( 'change', function () {
        that
          .search( '^'+$(this).val() , true, false )
          .draw();
      } );

    // Get the search data for the first column and add to the select list
    this
      .cache( 'search' )
      .sort()
      .unique()
      .each( function ( d ) {
        select.append( $('<option value="'+d+'">'+d+'</option>') );
      } );
  } );


  let user_id = 0;
  $('.user_row').on("click", function () {
    $('#user_modal').foundation('open');
    $('.users-spinner').addClass("active")
    user_id = $(this).data("user")
    $('#user_modal_content').hide()
    makeRequest( "get", `user?user=${user_id}`, null , 'dispatcher-tools/v1/')
    .then(response=>{
      $('#user_modal_content').show()
      $('.users-spinner').removeClass("active")
      $("#user_name").html(_.escape(response.display_name))

      //status
      $('#status-select').val(response.user_status)
      if ( response.user_status !== "0" ){
      }

      //locations
      let typeahead = Typeahead['.js-typeahead-location_grid']
      if ( typeahead ){
        for (let i = 0; i < typeahead.items.length; i ){
          typeahead.cancelMultiselectItem(0)
        }

      }
      response.locations.forEach( location=>{
        typeahead.addMultiselectItemLayout({ID:location.grid_id.toString(), name:location.name})
      })

      //availability
      display_dates_unavailable( response.dates_unavailable )

      //stats
      $('#update_needed_count').html(response.update_needed["total"])
      $('#needs_accepted_count').html(response.needs_accepted["total"])
      $('#active_contacts').html(response.active_contacts)
      $('#unread_notifications').html(response.unread_notifications)
      day_activity_chart(response.days_active)

      //Activity history
      let activity_div = $('#activity')
      let activity_html = ``;
      response.user_activity.forEach((a)=>{
        activity_html += `<div>
          <strong>${moment.unix(a.hist_time).format('YYYY-MM-DD')}</strong>
          ${a.object_note}
        </div>`
      })
      activity_div.html(activity_html)



    })

  })

  let update_user = ( user_id, key, value )=>{
    let data =  {
      [key]: value
    }
    return makeRequest( "POST", `user?user=${user_id}`, data , 'dispatcher-tools/v1/' )

  }


  /**
   * Status
   */
  $('#status-select').on('change', function () {
    let value = $(this).val()
    update_user( user_id, 'user_status', value)
  })

  /**
   * Set availability dates
   */
  let dateFields = [ "start_date", "end_date" ]
  dateFields.forEach(key=>{
    let datePicker = $(`#${key}.date-picker`)
    datePicker.datepicker({
      onSelect: function (date) {
        let start_date = $('#start_date').val()
        let end_date = $('#end_date').val()
        if ( start_date && end_date ){
          $('#add_unavailable_dates').removeAttr("disabled");
        }
      },
      dateFormat: 'yy-mm-dd',
      changeMonth: true,
      changeYear: true
    })

  })
  $('#add_unavailable_dates').on('click', function () {
    let start_date = $('#start_date').val()
    let end_date = $('#end_date').val()
    $('#add_unavailable_dates_spinner').addClass('active')
    update_user( user_id, 'add_unavailability', {start_date, end_date}).then((resp)=>{
      $('#add_unavailable_dates_spinner').removeClass('active')
      $('#start_date').val('')
      $('#end_date').val('')
      display_dates_unavailable(resp)
    })
  })
  let display_dates_unavailable = (list = [] )=>{
    let date_unavailable_table = $('#unavailable-list')
    date_unavailable_table.empty()
    let rows = ``
    list.forEach(range=>{
      rows += `<tr>
        <td>${range.start_date}</td>
        <td>${range.end_date}</td>
        <td><button class="button remove_dates_unavailable" data-id="${range.id}">Remove</button></td>
      </tr>`
    })
    date_unavailable_table.html(rows)
  }
  $( document).on( 'click', '.remove_dates_unavailable', function () {
    let id = $(this).data('id');
    update_user( user_id, 'remove_unavailability', id).then((resp)=>{
      display_dates_unavailable(resp)
    })
  })


  /**
   * Locations
   */
  let typeaheadTotals = {}
  if (!window.Typeahead['.js-typeahead-location_grid']){
    $.typeahead({
      input: '.js-typeahead-location_grid',
      minLength: 0,
      accent: true,
      searchOnFocus: true,
      maxItem: 20,
      template: function (query, item) {
        return `<span>${_.escape(item.name)}</span>`
      },
      dropdownFilter: [{
        key: 'group',
        value: 'focus',
        template: 'Regions of Focus',
        all: 'All Locations'
      }],
      source: {
        focus: {
          display: "name",
          ajax: {
            url: wpApiShare.root + 'dt/v1/mapping_module/search_location_grid_by_name',
            data: {
              s: "{{query}}",
              filter: function () {
                return _.get(window.Typeahead['.js-typeahead-location_grid'].filters.dropdown, 'value', 'all')
              }
            },
            beforeSend: function (xhr) {
              xhr.setRequestHeader('X-WP-Nonce', wpApiShare.nonce);
            },
            callback: {
              done: function (data) {
                if (typeof typeaheadTotals !== "undefined") {
                  typeaheadTotals.field = data.total
                }
                return data.location_grid
              }
            }
          }
        }
      },
      display: "name",
      templateValue: "{{name}}",
      dynamic: true,
      multiselect: {
        matchOn: ["ID"],
        data: function () {
          return [];
        }, callback: {
          onCancel: function (node, item) {
            update_user( user_id, 'remove_location', item.ID)
          }
        }
      },
      callback: {
        onClick: function(node, a, item, event){
          update_user( user_id, 'add_location', item.ID)
        },
        onReady(){
          this.filters.dropdown = {key: "group", value: "focus", template: "Regions of Focus"}
          this.container
            .removeClass("filter")
            .find("." + this.options.selector.filterButton)
            .html("Regions of Focus");
        },
        onResult: function (node, query, result, resultCount) {
          resultCount = typeaheadTotals.location_grid
          let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
          $('#location_grid-result-container').html(text);
        },
        onHideLayout: function () {
          $('#location_grid-result-container').html("");
        }
      }
    });
  }

  let day_activity_chart = (days_active)=>{
    am4core.ready(function() {

      am4core.useTheme(am4themes_animated);

      var chart = am4core.create("day_activity_chart", am4charts.XYChart);
      chart.maskBullets = false;

      var xAxis = chart.xAxes.push(new am4charts.CategoryAxis());
      var yAxis = chart.yAxes.push(new am4charts.CategoryAxis());

      xAxis.dataFields.category = "week_start";
      yAxis.dataFields.category = "weekday";

      // xAxis.renderer.grid.template.disabled = true;
      xAxis.renderer.minGridDistance = 100;

      // yAxis.renderer.grid.template.disabled = true;
      yAxis.renderer.inversed = true;
      yAxis.renderer.minGridDistance = 10;

      var series = chart.series.push(new am4charts.ColumnSeries());
      series.dataFields.categoryY = "weekday";
      series.dataFields.categoryX = "week_start";
      series.dataFields.value = "activity";
      series.sequencedInterpolation = true;
      series.defaultState.transitionDuration = 3000;

      var bgColor = new am4core.InterfaceColorSet().getFor("background");

      var columnTemplate = series.columns.template;
      columnTemplate.strokeWidth = 1;
      columnTemplate.strokeOpacity = 0.2;
      // columnTemplate.stroke = bgColor;
      columnTemplate.tooltipText = "{weekday}, {day}: {activity_count}";
      columnTemplate.width = am4core.percent(100);
      columnTemplate.height = am4core.percent(100);

      series.heatRules.push({
        target: columnTemplate,
        property: "fill",
        // min: am4core.color('#deeff8'),
        min: am4core.color(bgColor),
        max: chart.colors.getIndex(0)
      });

      chart.data = days_active
      });
  }
})
