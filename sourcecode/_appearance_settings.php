<section class="card">
  <div class="ph"><span class="pico"><?=ic('settings')?></span><div><h3>Appearance</h3><p>Personalize how eSmartLib looks on this browser.</p></div></div>
  <div class="appearance-grid">
    <div class="appearance-setting appearance-dark wide">
      <div><h3>Dark mode</h3><p>Choose a darker look that is easier on the eyes.</p></div>
      <label class="appearance-switch"><input type="checkbox" id="appearance-dark-mode" aria-label="Enable dark mode"><span aria-hidden="true"></span></label>
    </div>
    <div class="appearance-setting">
      <h3>Theme color</h3><p>Choose your preferred accent color.</p>
      <div class="appearance-colors" role="radiogroup" aria-label="Theme color">
        <label title="Green"><input type="radio" name="appearance-accent" value="green" aria-label="Green"><span style="--swatch:#1f7a46"></span></label>
        <label title="Blue"><input type="radio" name="appearance-accent" value="blue" aria-label="Blue"><span style="--swatch:#2563eb"></span></label>
        <label title="Pink"><input type="radio" name="appearance-accent" value="pink" aria-label="Pink"><span style="--swatch:#db2777"></span></label>
        <label title="Teal"><input type="radio" name="appearance-accent" value="teal" aria-label="Teal"><span style="--swatch:#0f9888"></span></label>
        <label title="Orange"><input type="radio" name="appearance-accent" value="orange" aria-label="Orange"><span style="--swatch:#ea580c"></span></label>
        <label title="Purple"><input type="radio" name="appearance-accent" value="purple" aria-label="Purple"><span style="--swatch:#7c3aed"></span></label>
        <label title="Red"><input type="radio" name="appearance-accent" value="red" aria-label="Red"><span style="--swatch:#dc2626"></span></label>
        <label title="Indigo"><input type="radio" name="appearance-accent" value="indigo" aria-label="Indigo"><span style="--swatch:#4f46e5"></span></label>
        <label title="Cyan"><input type="radio" name="appearance-accent" value="cyan" aria-label="Cyan"><span style="--swatch:#0891b2"></span></label>
        <label title="Amber"><input type="radio" name="appearance-accent" value="amber" aria-label="Amber"><span style="--swatch:#d97706"></span></label>
      </div>
    </div>
    <div class="appearance-setting">
      <h3>Text size</h3><p>Adjust text throughout the system without zooming the page.</p>
      <div class="appearance-choices" role="radiogroup" aria-label="Text size">
        <label title="Small"><input type="radio" name="appearance-text-size" value="small" aria-label="Small"><span style="font-size:12px">Aa</span></label>
        <label title="Normal"><input type="radio" name="appearance-text-size" value="normal" aria-label="Normal"><span style="font-size:15px">Aa</span></label>
        <label title="Large"><input type="radio" name="appearance-text-size" value="large" aria-label="Large"><span style="font-size:18px">Aa</span></label>
        <label title="Extra large"><input type="radio" name="appearance-text-size" value="x-large" aria-label="Extra large"><span style="font-size:21px">Aa</span></label>
      </div>
    </div>
    <div class="appearance-setting wide">
      <h3>Font style</h3><p>Choose the font used throughout eSmartLib on this browser.</p>
      <div class="appearance-choices appearance-fonts" role="radiogroup" aria-label="Font style">
        <label><input type="radio" name="appearance-font-family" value="jakarta" aria-label="Plus Jakarta Sans"><span style="font-family:'Plus Jakarta Sans',sans-serif">Plus Jakarta</span></label>
        <label><input type="radio" name="appearance-font-family" value="arial" aria-label="Arial"><span style="font-family:Arial,sans-serif">Arial</span></label>
        <label><input type="radio" name="appearance-font-family" value="verdana" aria-label="Verdana"><span style="font-family:Verdana,sans-serif">Verdana</span></label>
        <label><input type="radio" name="appearance-font-family" value="georgia" aria-label="Georgia"><span style="font-family:Georgia,serif">Georgia</span></label>
        <label><input type="radio" name="appearance-font-family" value="tahoma" aria-label="Tahoma"><span style="font-family:Tahoma,sans-serif">Tahoma</span></label>
        <label><input type="radio" name="appearance-font-family" value="trebuchet" aria-label="Trebuchet MS"><span style="font-family:'Trebuchet MS',sans-serif">Trebuchet</span></label>
      </div>
    </div>
    <div class="appearance-setting wide">
      <h3>UI style</h3><p>Choose the shape of cards and controls.</p>
      <div class="appearance-choices appearance-style" role="radiogroup" aria-label="UI style">
        <label><input type="radio" name="appearance-ui-style" value="rounded" aria-label="Rounded"><span>Rounded</span></label>
        <label><input type="radio" name="appearance-ui-style" value="soft" aria-label="Soft"><span>Soft</span></label>
        <label><input type="radio" name="appearance-ui-style" value="crisp" aria-label="Crisp"><span>Crisp</span></label>
      </div>
    </div>
  </div>
  <p class="appearance-status" id="appearance-status" role="status" aria-live="polite"></p>
  <button type="button" class="ghost" id="appearance-reset">Reset to default</button>
</section>
