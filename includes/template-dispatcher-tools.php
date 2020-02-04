<?php
/*
Template Name: Dispatcher Tools
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
                    <?php if ( strpos( $dt_url_path, 'dispatcher-tools/multipliers' ) !== false ) :
                        $users = DT_Dispatcher_Endpoints::get_users(); ?>
                        <?php if ( current_user_can( "list_users" ) ) :?>
                            <h3>Users</h3>
                        <?php else : ?>
                            <h3>Multipliers</h3>
                        <?php endif; ?>
                        <p><a href="#" id="refresh_cached_data">Refresh cached data</a><span id="loading-page" class="loading-spinner"></span></p>
                        <div style="display: inline-block" class="loading-spinner users-spinner"></div>
                        <table id="multipliers_table" class="display">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Display Name</th>
                                    <th class="select-filter">Status</th>
                                    <th class="select-filter">Workload</th>
                                    <th>Accept Needed</th>
                                    <th>Update Needed</th>
                                    <th>Active</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $workload_status_options = dt_get_site_custom_lists()["user_workload_status"] ?? [];
                            $index = 0;
                            foreach ( $users as $user_i => $user ) : ?>
                            <tr class="user_row" style="cursor: pointer"data-user="<?php echo esc_html( $user["ID"] ) ?>">
                                <td></td>
                                <td data-user="<?php echo esc_html( $user["ID"] ) ?>"><?php echo esc_html( $user["display_name"] ) ?></td>
                                <td><?php echo esc_html( $user["user_status"] ?? "" ) ?></td>
                                <td><?php echo esc_html( isset( $user["workload_status"], $workload_status_options[ $user["workload_status"] ] ) ? $workload_status_options[ $user["workload_status"] ]["label"] : "" ) ?></td>
                                <td><?php echo esc_html( $user["number_new_assigned"] ) ?></td>
                                <td>
                                    <?php if ( $user["number_update"] > 5 ) : ?>
                                        <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' )?>" />
                                    <?php endif; ?>
                                    <?php echo esc_html( $user["number_update"] ) ?>
                                </td>
                                <td><?php echo esc_html( $user["number_active"] ) ?></td>
                                <td data-sort="<?php echo esc_html( $user["last_activity"] ?? "" ) ?>">
                                    <?php if ( !isset( $user["last_activity"] ) ) :
                                        esc_html_e( "No activity", 'disciple_tools' );
                                    elseif ( $user["last_activity"] < time() - 60 * 60 * 24 * 90 ) : ?>
                                        <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' )?>" />
                                    <?php endif; ?>
                                    <?php echo esc_html( dt_format_date( $user["last_activity"] ?? "" ) ) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>


                    <?php endif; ?>


                    <?php if ( $dt_url_path === 'dispatcher-tools' || strpos( $dt_url_path, 'dispatcher-tools/dash' ) !== false ) :
                        $dash_stats = DT_Dispatcher_Endpoints::get_dash_stats(); ?>
                        <h3 class="section-header"><?php esc_html_e( "Contacts", 'disciple_tools' ) ?></h3>
                        <div class="center callout">
                            <div class="grid-x">
                                <div class="medium-3 cell center">
                                    <h5><?php esc_html_e( "Waiting on Accept", 'disciple_tools' ) ?><br>
                                        <span id="needs_accepted"><?php echo esc_html( $dash_stats["needs_accepted"] ) ?></span>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "Waiting On Update", 'disciple_tools' ) ?><br>
                                        <span id="updates_needed"><?php echo esc_html( $dash_stats["updates_needed"] ) ?></span>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "Active  Contacts", 'disciple_tools' ) ?><br>
                                        <span id="active_contacts"><?php echo esc_html( $dash_stats["active_contacts"] ) ?></span>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "All Contacts", 'disciple_tools' ) ?><br>
                                        <span id="all_contacts"><?php echo esc_html( $dash_stats["total_contacts"] ) ?></span>
                                    </h5>
                                </div>
                            </div>
                        </div>

                        <h3 class="section-header"><?php esc_html_e( "Contacts assigned to multipliers", 'disciple_tools' ) ?></h3>
                        <div class="center callout">
                            <div class="grid-x">
                                <div class="medium-3 cell center">
                                    <h5><?php esc_html_e( "All Time", 'disciple_tools' ) ?><br>
                                        <?php echo esc_html( $dash_stats["assigned_counts"]["all_time"] ?? 0 ) ?>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "This Year", 'disciple_tools' ) ?><br>
                                        <?php echo esc_html( $dash_stats["assigned_counts"]["this_year"] ?? 0 ) ?>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "Last Month", 'disciple_tools' ) ?><br>
                                        <?php echo esc_html( $dash_stats["assigned_counts"]["last_month"] ?? 0 ) ?>
                                    </h5>
                                </div>
                                <div class="medium-3 cell center left-border-grey">
                                    <h5><?php esc_html_e( "This Month", 'disciple_tools' ) ?><br>
                                        <?php echo esc_html( $dash_stats["assigned_counts"]["this_month"] ?? 0 ) ?>
                                    </h5>
                                </div>
                            </div>
                        </div>

                        <h3 class="section-header"><?php esc_html_e( "Multipliers", 'disciple_tools' ) ?></h3>
                        <a class="button" href="multipliers">See All</a>
                        <div class="center callout">
                            <div class="grid-x">
                                <?php if ( isset( $dash_stats["multiplier_stats"]["status"][""] ) && !empty( $dash_stats["multiplier_stats"]["status"][""] ) ) : ?>
                                <div class="auto cell center">
                                    <h5>No Status<br>
                                        <span id="needs_accepted"><?php echo esc_html( $dash_stats["multiplier_stats"]["status"][""] ) ?></span>
                                    </h5>
                                </div>
                                <?php endif; ?>
                                <?php foreach ( $dash_stats["multiplier_stats"]["status"] as $status_key => $num ) :
                                    if ( $status_key && $num ) : ?>
                                    <div class="cell auto center left-border-grey">
                                        <h5><?php echo esc_html( $status_key ) ?><br>
                                            <span id="updates_needed"><?php echo esc_html( $num ) ?></span>
                                        </h5>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <h3 class="section-header"><?php esc_html_e( "Statuses", 'disciple_tools' ) ?></h3>
                        <div id="status_chart_div" style="height:500px"></div>

                    <?php endif; ?>
                    </div><!-- Container for charts -->

                </div>

            </section>

        </div>

        <?php if ( strpos( $dt_url_path, 'dispatcher-tools/multipliers' ) !== false ) : ?>
        <div class="full reveal" id="user_modal" data-reveal style="background-color: #e2e2e2">
            <span style="display: inline-block" class="loading-spinner users-spinner"></span>
            <div id="user_modal_content">

                <h1 id="user_name" style="display: inline-block"><?php esc_html_e( "Multiplier Name", 'disciple_tools' ) ?></h1>
                <button class="button" data-close aria-label="Close reveal" type="button" style="margin-left:30px">
                    <span aria-hidden="true">Return to list</span>
                </button>

                <button class="close-button" data-close aria-label="Close reveal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>

                <hr>

                <div style="display: flex; justify-content: flex-start" id="hero_stats">

                    <div class="bordered-box">
                        <div class="section-subheader">
                            <?php esc_html_e( 'Update Needed', 'disciple_tools' )?>
                        </div>
                        <p style="text-align: center" id="update_needed_count"></p>
                    </div>

                    <div class="bordered-box">
                        <div class="section-subheader">
                            <?php esc_html_e( 'Pending', 'disciple_tools' )?>
                        </div>
                        <p style="text-align: center" id="needs_accepted_count"></p>
                    </div>
                    <div class="bordered-box">
                        <div class="section-subheader">
                            <?php esc_html_e( 'Active Contacts', 'disciple_tools' )?>
                        </div>
                        <p style="text-align: center" id="active_contacts"></p>
                    </div>
                    <div class="bordered-box">
                        <div class="section-subheader">
                            <?php esc_html_e( 'Unread Notifications', 'disciple_tools' )?>
                        </div>
                        <p style="text-align: center" id="unread_notifications"></p>
                    </div>
                    <div class="bordered-box">
                        <div class="section-subheader">Contacts Assigned</div>
                        <ul class="ul-no-bullets">
                            <li>This month: <span id="assigned_this_month"></span></li>
                            <li>Last month: <span id="assigned_last_month"></span></li>
                            <li>This year: <span id="assigned_this_year"></span></li>
                            <li>Last year: <span id="assigned_all_time"></span></li>
                        </ul>
                    </div>

                </div>

                <div style="display: flex; justify-content: space-between">

                    <div style="flex-basis: 50%; padding-right:10px" class="user_modal_column">
                        <div class="bordered-box">
                            <h3>Status</h3>
                            <select id="status-select" class="user-select">
                                <option></option>
                                <option value="new">New</option>
                                <option value="active">Active</option>
                                <option value="away">Away</option>
                                <option value="away">Fading</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="bordered-box">
                            <h3>Workload Status</h3>
                            <select id="workload-select" class="user-select">
                            <?php $workload_status_options = dt_get_site_custom_lists()["user_workload_status"] ?? [] ?>
                                <option></option>
                                <?php foreach ( $workload_status_options as $key => $val ) : ?>
                                    <option value="<?php echo esc_html( $key ) ?>"><?php echo esc_html( $val["label"] ) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>



                        <!-- roles -->
                        <?php if ( current_user_can( "promote_users" ) ) : ?>
                        <div class="bordered-box">
                            <h3><?php esc_html_e( 'Roles', 'disciple_tools' ); ?></h3>
                            <?php
                            $user_roles = [];

                            $dt_roles = dt_multi_role_get_editable_role_names();
                            ?>

                            <p>For a description of each of the roles, please see the <a href="https://disciple-tools.readthedocs.io/en/latest/Disciple_Tools_Theme/getting_started/roles.html" target="_blank">Roles Documentation</a>  </p>

                            <ul id="user_roles_list" class="no-bullet">
                            <?php foreach ( $dt_roles as $role_key => $name ) : ?>
                                <li>
                                    <label style="color:<?php echo esc_html( $role_key === 'administrator' ? 'grey' : 'inherit' ); ?>">
                                        <input type="checkbox" name="dt_multi_role_user_roles[]"
                                               value="<?php echo esc_attr( $role_key ); ?>"
                                               <?php checked( in_array( $role_key, $user_roles ) ); ?>
                                               <?php disabled( $role_key === 'administrator' ); ?> />
                                        <?php echo esc_html( $name ); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                            <button class="button loader" id="save_roles"><?php esc_html_e( 'Save Roles', 'disciple_tools' ); ?></button>
                        </div>
                        <?php endif; ?>

                        <!-- locations -->
                        <div class="bordered-box">
                        <h3>Locations the multiplier is responsible for</h3>
                        <div class="location_grid">
                            <var id="location_grid-result-container" class="result-container"></var>
                            <div id="location_grid_t" name="form-location_grid" class="scrollable-typeahead typeahead-margin-when-active">
                                <div class="typeahead__container">
                                    <div class="typeahead__field">
                                        <span class="typeahead__query">
                                            <input class="js-typeahead-location_grid input-height"
                                                   name="location_grid[query]" placeholder="<?php esc_html_e( "Search Locations", 'disciple_tools' ) ?>"
                                                   autocomplete="off">
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="bordered-box">
                            <h3><?php esc_html_e( "Availability", 'disciple_tools' ) ?></h3>
                            <p><?php esc_html_e( "Set the dates you will be unavailable so the Dispatcher will know your availability to receive new contacts", 'disciple_tools' ) ?></p>
                            <div style="display: flex; align-items: center">
                                <div>
                                    <strong>Add dates unavailable:</strong>
                                </div>
                                <div style="flex-shrink: 1; margin: 0 10px">
                                    <div class="section-subheader cell">
                                        <?php esc_html_e( 'Start and end date', 'disciple_tools' )?>
                                    </div>
                                    <div class="date_range"><input type="text" class="date-picker" id="date_range" autocomplete="off"></div>
                                    <div id="add_unavailable_dates_spinner" style="display: inline-block" class="loading-spinner"></div>
                                </div>

                            </div>
                            <p><?php esc_html_e( "Travel or Away Dates", 'disciple_tools' ) ?></p>
                            <div >
                                <table>
                                    <thead>
                                    <tr>
                                        <th><?php esc_html_e( "Start date", 'disciple_tools' ) ?></th>
                                        <th><?php esc_html_e( "End date", 'disciple_tools' ) ?></th>
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
                            <div class="subheader">Daily Activity</div>
                            <div id="day_activity_chart" style="height: 300px"></div>

                            <div class="subheader">Assigned and not accepted</div>
                            <ul id="unaccepted_contacts"></ul>
                            <div class="subheader">Last 10 assigned to accepted duration (<span id="avg_contact_accept"></span> days average)</div>
                            <ul id="contact_accepts"></ul>
                            <div class="subheader">Accepted with no contact attempt</div>
                            <ul id="unattempted_contacts"></ul>
                            <div class="subheader">Last 10 assigned to contact attempt (<span id="avg_contact_attempt"></span> days average)</div>
                            <ul id="contact_attempts"></ul>
                            <div class="subheader">Oldest 10 update needed</div>
                            <ul id="update_needed_list"></ul>


                            <div class="subheader">Contact Statuses</div>
                            <div id="status_chart_div" style="height:300px"></div>
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
        <?php endif; ?>

    </div> <!-- end #inner-content -->

</div> <!-- end #content -->

<?php get_footer(); ?>

