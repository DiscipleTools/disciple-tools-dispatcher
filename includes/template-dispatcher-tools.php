<?php
/*
Template Name: Metrics
*/
if ( ! current_user_can( 'access_contacts' ) ) {
    wp_safe_redirect( '/settings' );
}
$dt_url_path = dt_get_url_path();
?>

<?php get_header(); ?>

<div style="padding:15px">

    <div id="inner-content" class="grid-x grid-margin-x grid-margin-y">

        <div class="large-2 medium-3 small-12 cell" id="side-nav-container">

            <section id="metrics-side-section" class="medium-12 cell">

                <div class="bordered-box">

                    <ul id="metrics-sidemenu" class="vertical menu accordion-menu" data-accordion-menu data-multi-expand="true" >

                        <?php

                        // WordPress.XSS.EscapeOutput.OutputNotEscaped
                        // @phpcs:ignore
                        echo apply_filters( 'dt_metrics_menu', '' );

                        ?>

                    </ul>

                </div>

            </section>

        </div>

        <div class="large-10 medium-9 small-12 cell ">

            <section id="metrics-container" class="medium-12 cell">

                <div class="bordered-box">

                    <div id="chart">
                    <?php if ( $dt_url_path === 'dispatcher-tools/multipliers' ) :
                        $users = DT_Dispatcher_Tools_Endpoints::get_users(); ?>
                        <h3>Multipliers</h3>
                        <div style="display: inline-block" class="loading-spinner users-spinner"></div>
                        <table id="multipliers_table" class="display">
                            <thead>
                                <tr>
                                    <th>Display Name</th>
                                    <th class="select-filter">Status</th>
                                    <th>Accept Needed</th>
                                    <th>Update Needed</th>
                                    <th>Active</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $users as $user_i => $user ) : ?>
                            <tr class="user_row" style="cursor: pointer"data-user="<?php echo esc_html( $user["ID"] ) ?>">
                                <td><?php echo esc_html( $user["display_name"] ) ?></td>
                                <td><?php echo esc_html( $user["user_status"] ) ?></td>
                                <td><?php echo esc_html( $user["number_new_assigned"] ) ?></td>
                                <td>
                                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' )?>" />
                                    <?php echo esc_html( $user["number_update"] ) ?>
                                </td>
                                <td><?php echo esc_html( $user["number_active"] ) ?></td>
                                <td data-sort="<?php echo esc_html( $user["last_activity"] ?? "" ) ?>">
                                    <?php echo esc_html( dt_format_date( $user["last_activity"] ?? "" ) ) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>


                    <?php endif; ?>
                    </div><!-- Container for charts -->

                </div>

            </section>

        </div>

        <div class="full reveal" id="user_modal" data-reveal style="background-color: #e2e2e2">
            <span style="display: inline-block" class="loading-spinner users-spinner"></span>
            <div id="user_modal_content">
                <h1 id="user_name" style="display: inline-block"><?php esc_html_e( "Multiplier name", 'disciple_tools' ) ?></h1>

                <button class="close-button" data-close aria-label="Close reveal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>


                <div style="display: flex; justify-content: space-between">

                    <div style="flex-basis: 50%; padding-right:10px" class="user_modal_column">
                        <div class="bordered-box">
                            <h3>Status</h3>
                            <select id="status-select" class="user-select">
                                <option></option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="away">Away</option>
                            </select>
                        </div>
                        <div class="bordered-box">
                        <h3>Locations the multiplier is responsible for</h3>
                        <div class="location_grid">
                            <var id="location_grid-result-container" class="result-container"></var>
                            <div id="location_grid_t" name="form-location_grid" class="scrollable-typeahead typeahead-margin-when-active">
                                <div class="typeahead__container">
                                    <div class="typeahead__field">
                                        <span class="typeahead__query">
                                            <input class="js-typeahead-location_grid"
                                                   name="location_grid[query]" placeholder="<?php esc_html_e( "Search Locations", 'disciple_tools' ) ?>"
                                                   autocomplete="off">
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="bordered-box">
                        <h3>Availability</h3>
                        <p>Set the dates you will be unavailable so the Dispatcher will know your availability to receive new contacts</p>
                        <div style="display: flex; justify-content: space-around; align-items: flex-end">
                            <div style="flex-shrink: 1">
                                <div class="section-subheader cell">
                                    <?php esc_html_e( 'Start Date', 'disciple_tools' )?>
                                </div>
                                <div class="start_date"><input type="text" class="date-picker" id="start_date"></div>
                            </div>
                            <div>
                            <div class="section-subheader cell">
                                <?php esc_html_e( 'End Date', 'disciple_tools' )?>
                            </div>
                            <div class="end_date"><input type="text" class="date-picker" id="end_date"></div>
                            </div>
                            <div>
                                <button id="add_unavailable_dates" class="button" disabled>Add Unavailable dates</button>
                                <div id="add_unavailable_dates_spinner" style="display: inline-block" class="loading-spinner"></div>
                            </div>

                        </div>
                        <p>Unavailable Dates</p>
                        <div >
                            <table>
                                <thead>
                                <tr>
                                    <th>Start date</th>
                                    <th>End date</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody id="unavailable-list">

                                </tbody>

                            </table>
                        </div>
                        </div>
                        <div class="bordered-box">
                        <h3>Stats</h3>
                        </div>
                    </div>
                    <div style="flex-basis: 50%; padding-left: 10px" class="user_modal_column">
                        <div class="bordered-box">
                            <h3>History</h3>
                            <div id="activity"></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- end #inner-content -->

</div> <!-- end #content -->

<?php get_footer(); ?>

