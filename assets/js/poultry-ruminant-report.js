/**
 * Poultry and ruminant report filter behavior.
 * Externalized for CSP compatibility.
 */
function applyFilters() {
    const farmType = $('#farmTypeFilter').val();
    const reportMode = $('#reportMode').val();
    const monthValue = $('#monthFilter').val();
    const month = monthValue ? monthValue.substring(0, 7) : '';
    const year = $('#yearFilter').val();
    window.location.href = `poultry_ruminant_report.php?report_mode=${reportMode}&month=${month}&year=${year}&farm_type=${farmType}`;
}
$('#farmTypeFilter, #reportMode, #monthFilter, #yearFilter').on('change', function() {
    const mode = $('#reportMode').val();
    $('#monthFilter').toggleClass('d-none', mode !== 'monthly');
    $('#yearFilter').toggleClass('d-none', mode !== 'yearly');
    applyFilters();
});
