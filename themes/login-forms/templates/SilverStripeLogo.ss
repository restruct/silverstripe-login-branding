<small class="app-credits">
    <% if $BrandingFragment('built_by') %>$BrandingFragment('built_by')<% end_if %>
    <% if $BrandingFragment('built_by') && $BrandingFragment('powered_by') %><br><% end_if %>
    <% if $BrandingFragment('powered_by') %>$BrandingFragment('powered_by')<% end_if %>
</small>
