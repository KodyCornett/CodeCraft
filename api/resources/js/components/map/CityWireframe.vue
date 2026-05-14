<template>
    <!--
        Static atmospheric city wireframe — Layer 1.
        Never changes. Never interactive. Pure ambiance.
        Overall opacity 0.15 so it reads as "ghost of a city" behind the node web.
    -->
    <svg
        viewBox="0 0 1200 800"
        xmlns="http://www.w3.org/2000/svg"
        class="city-wireframe"
        aria-hidden="true"
    >
        <g class="wireframe-group">

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 1 — Downtown Core (dense, center-left)        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="480" y="310" width="90"  height="60"  />
            <rect x="490" y="318" width="35"  height="22"  />  <!-- inner subdivision -->
            <rect x="530" y="318" width="32"  height="22"  />
            <rect x="580" y="305" width="70"  height="45"  />
            <rect x="588" y="313" width="25"  height="18"  />
            <rect x="480" y="385" width="55"  height="50"  />
            <rect x="545" y="380" width="40"  height="35"  />
            <rect x="545" y="420" width="40"  height="28"  />
            <rect x="595" y="362" width="58"  height="38"  />
            <rect x="604" y="370" width="20"  height="14"  />
            <rect x="628" y="370" width="18"  height="14"  />
            <rect x="595" y="408" width="58"  height="42"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 2 — Near North (moderate density)             -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="430" y="190" width="75"  height="50"  />
            <rect x="438" y="198" width="28"  height="18"  />
            <rect x="470" y="198" width="28"  height="18"  />
            <rect x="515" y="185" width="55"  height="42"  />
            <rect x="580" y="195" width="48"  height="36"  />
            <rect x="640" y="188" width="60"  height="48"  />
            <rect x="648" y="197" width="22"  height="16"  />
            <rect x="674" y="197" width="22"  height="16"  />
            <rect x="430" y="252" width="48"  height="36"  />
            <rect x="490" y="248" width="62"  height="40"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 3 — University District (medium, west-center) -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="230" y="240" width="80"  height="55"  />
            <rect x="238" y="248" width="30"  height="20"  />
            <rect x="272" y="248" width="30"  height="20"  />
            <rect x="320" y="235" width="65"  height="48"  />
            <rect x="328" y="244" width="24"  height="16"  />
            <rect x="230" y="308" width="55"  height="42"  />
            <rect x="296" y="305" width="70"  height="50"  />
            <rect x="304" y="314" width="25"  height="14"  />
            <rect x="333" y="314" width="25"  height="14"  />
            <rect x="375" y="245" width="48"  height="35"  />
            <rect x="375" y="292" width="48"  height="48"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 4 — Browne's Addition (west, tight blocks)    -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="280" y="380" width="60"  height="44"  />
            <rect x="288" y="388" width="20"  height="14"  />
            <rect x="350" y="375" width="72"  height="50"  />
            <rect x="358" y="383" width="26"  height="16"  />
            <rect x="388" y="383" width="26"  height="16"  />
            <rect x="280" y="435" width="55"  height="38"  />
            <rect x="346" y="437" width="50"  height="36"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 5 — West Industrial (sparse, big blocks)      -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="60"  y="380" width="110" height="70"  />
            <rect x="70"  y="390" width="40"  height="25"  />
            <rect x="115" y="390" width="48"  height="25"  />
            <rect x="60"  y="462" width="80"  height="55"  />
            <rect x="155" y="395" width="65"  height="48"  />
            <rect x="155" y="455" width="65"  height="40"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 6 — South Hill (medium, south-center)         -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="460" y="555" width="75"  height="52"  />
            <rect x="468" y="563" width="28"  height="18"  />
            <rect x="500" y="563" width="28"  height="18"  />
            <rect x="548" y="548" width="62"  height="46"  />
            <rect x="556" y="556" width="22"  height="15"  />
            <rect x="582" y="556" width="22"  height="15"  />
            <rect x="460" y="618" width="58"  height="42"  />
            <rect x="530" y="605" width="70"  height="55"  />
            <rect x="538" y="613" width="26"  height="18"  />
            <rect x="568" y="613" width="26"  height="18"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 7 — Spokane Valley (east, spread out)         -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="790" y="360" width="85"  height="58"  />
            <rect x="798" y="368" width="32"  height="20"  />
            <rect x="834" y="368" width="32"  height="20"  />
            <rect x="885" y="350" width="72"  height="52"  />
            <rect x="893" y="358" width="26"  height="16"  />
            <rect x="790" y="430" width="60"  height="44"  />
            <rect x="862" y="415" width="80"  height="60"  />
            <rect x="870" y="423" width="30"  height="20"  />
            <rect x="904" y="423" width="30"  height="20"  />
            <rect x="955" y="355" width="55"  height="42"  />
            <rect x="955" y="408" width="55"  height="50"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 8 — North Spokane (medium, upper mid)         -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="590" y="85"  width="70"  height="50"  />
            <rect x="598" y="93"  width="25"  height="16"  />
            <rect x="627" y="93"  width="25"  height="16"  />
            <rect x="670" y="80"  width="58"  height="44"  />
            <rect x="740" y="88"  width="65"  height="48"  />
            <rect x="748" y="96"  width="24"  height="15"  />
            <rect x="776" y="96"  width="24"  height="15"  />
            <rect x="590" y="148" width="55"  height="40"  />
            <rect x="656" y="142" width="68"  height="48"  />
            <rect x="736" y="148" width="60"  height="42"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 9 — Northeast (sparse)                        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="870" y="110" width="80"  height="55"  />
            <rect x="878" y="118" width="30"  height="18"  />
            <rect x="960" y="105" width="65"  height="48"  />
            <rect x="870" y="178" width="60"  height="42"  />
            <rect x="942" y="168" width="72"  height="52"  />
            <rect x="950" y="176" width="26"  height="16"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 10 — Far East (sparse, large lots)            -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="1035" y="290" width="100" height="65" />
            <rect x="1045" y="300" width="38"  height="22" />
            <rect x="1088" y="300" width="38"  height="22" />
            <rect x="1035" y="368" width="80"  height="55" />
            <rect x="1125" y="300" width="55"  height="44" />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 11 — Northwest (very sparse)                  -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="65"  y="120" width="90"  height="60"  />
            <rect x="75"  y="130" width="32"  height="18"  />
            <rect x="168" y="115" width="72"  height="52"  />
            <rect x="65"  y="194" width="65"  height="48"  />
            <rect x="145" y="182" width="80"  height="58"  />
            <rect x="155" y="192" width="30"  height="18"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 12 — South Central (medium density)           -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="660" y="555" width="68"  height="48"  />
            <rect x="668" y="563" width="24"  height="15"  />
            <rect x="740" y="548" width="55"  height="42"  />
            <rect x="660" y="615" width="60"  height="44"  />
            <rect x="732" y="602" width="72"  height="55"  />
            <rect x="740" y="610" width="26"  height="16"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 13 — South Industrial (big sparse lots)       -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="280" y="530" width="95"  height="65"  />
            <rect x="290" y="540" width="36"  height="22"  />
            <rect x="330" y="540" width="36"  height="22"  />
            <rect x="280" y="608" width="80"  height="55"  />
            <rect x="388" y="545" width="58"  height="45"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 14 — Far North fringe (very sparse)           -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="350" y="55"  width="85"  height="52"  />
            <rect x="358" y="63"  width="30"  height="16"  />
            <rect x="448" y="52"  width="60"  height="44"  />
            <rect x="520" y="58"  width="55"  height="42"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ZONE 15 — Central connector (small infill blocks)  -->
            <!-- ═══════════════════════════════════════════════════ -->
            <rect x="408" y="310" width="58"  height="42"  />
            <rect x="416" y="318" width="20"  height="13"  />
            <rect x="408" y="362" width="58"  height="38"  />
            <rect x="700" y="310" width="72"  height="48"  />
            <rect x="708" y="318" width="26"  height="15"  />
            <rect x="738" y="318" width="26"  height="15"  />
            <rect x="700" y="370" width="72"  height="52"  />

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- DIAGONAL LINES — elevated roads / rail cuts        -->
            <!-- ═══════════════════════════════════════════════════ -->
            <!-- Northwest–Southeast arterial (main diagonal) -->
            <line x1="120" y1="60"  x2="760" y2="480" />
            <!-- Northeast rail cut -->
            <line x1="680" y1="55"  x2="1140" y2="340" />
            <!-- West side overpass -->
            <line x1="55"  y1="310" x2="340" y2="560" />
            <!-- South Valley connector -->
            <line x1="620" y1="720" x2="1050" y2="520" />
            <!-- Central crosscut -->
            <line x1="200" y1="460" x2="560" y2="220" />

        </g>
    </svg>
</template>

<script setup>
</script>

<style scoped>
.city-wireframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0.15;
    pointer-events: none;
}

.wireframe-group rect {
    fill: none;
    stroke: #00FFFF;
    stroke-width: 0.5;
    opacity: 0.12;
}

.wireframe-group line {
    stroke: #00FFFF;
    stroke-width: 0.6;
    opacity: 0.08;
    stroke-dasharray: 12 6;
}
</style>
