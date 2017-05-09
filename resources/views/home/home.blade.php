<!DOCTYPE html>
<html>
    <head>
        <title>Destiny Activity Report</title>
        <meta charset="utf-8" />
        <script src="{{ mix('assets/compileds/manifest.js') }}"></script>
        <script src="{{ mix('assets/compileds/vendor.js') }}"></script>
        <script src="{{ mix('assets/compileds/application.js') }}" defer></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/95f55b85b2.css" />
        <link rel="stylesheet" href="{{ mix('assets/compileds/vendor.css') }}" />
        <link rel="stylesheet" href="{{ mix('assets/compileds/application.css') }}" />
    </head>
    <body>
        <my-application>
            <h1 class="baseTitle">Destiny Activity Report</h1>

            <my-boxes>
                <my-box width="20%" label="Clan name:">
                    <my-input ref="self" type="text" class="clanMaster processComponents" query="clan"></my-input>
                </my-box>
                <my-box width="20%" label="Clan allies:">
                    <my-textarea class="clanAllies processComponents" expansible query="allies"></my-textarea>
                </my-box>
                <my-box width="10%">
                    <my-button ref="self" class="processComponents" @click.native="$refs.self.action('Process:clan')">Process</my-button>
                </my-box>
                <div class="break"></div>

                <my-box label="Logging:">
                    <my-messages></my-messages>
                </my-box>
            </my-boxes>

            <my-clan-account
                :score-entanglement="{{ $scoreEntanglement }}"
                :score-recentivity="{{ $scoreRecentivity }}"></my-clan-account>
            <my-clan-list></my-clan-list>

            <p style="margin-top: 15px;">
                <small>Activities are analyzed based on lasts 60 days or 25 ingame activities.</small>
            </p>
        </my-application>
    </body>
</html>
