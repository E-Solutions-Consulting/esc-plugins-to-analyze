<div>
    <a href="javascript:void(0);" id="toggleAdvanced" class="toggle-btn">
        ⚙️ Advanced Options ▼
    </a>
</div>
<div id="advancedFilterSection" style="display:none;padding: 10px 15px;">
    <div class="input-filters">
        <div>
            <label>Batch size:</label>
            <select name="batch_size">
                <option value="1" >1 record</option>
                <option value="2" >2 record</option>
                <option value="3" >3 records</option>
                <option value="4" >4 records</option>
                <option value="5">5 records</option>
                <option value="10" selected>10 records</option>
                <option value="15">15 records</option>
                <option value="20">20 records</option>
                <option value="25">25 records</option>
                <option value="50">50 records</option>
                <option value="100">100 records</option>
                <option value="250">250 records</option>
                <option value="500">500 records</option>
                <option value="1000">1,000 records</option>
                <option value="5000" >5,000 records</option>
                <option value="10000">10,000 records</option>
            </select>					
        </div>
        <div style="justify-content:space-around;">
            <label>Test Mode:</label>
            <label>
                <input type="checkbox" name="test_mode">Preview simulate changes
            </label>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleAdvanced');
    const advancedSection = document.getElementById('advancedFilterSection');
    let isAdvancedVisible = false;
    toggleBtn.addEventListener('click', function() {
        isAdvancedVisible = !isAdvancedVisible;
        
        if (isAdvancedVisible) {
        advancedSection.style.display = 'flex';
        toggleBtn.innerHTML = '⚙️ Advanced Options ▲';
        } else {
        advancedSection.style.display = 'none';
        toggleBtn.innerHTML = '⚙️ Advanced Options ▼';
        }
    });
    });
</script>