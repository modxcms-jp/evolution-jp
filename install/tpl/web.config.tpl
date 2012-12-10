<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Imported rule 1" stopProcessing="true">
                    <match url="^(manager|assets)/.*$" ignoreCase="false" />
                    <action type="None" />
                </rule>
                <rule name="Imported rule 2" stopProcessing="true">
                    <match url="\.(jpg|jpeg|png|gif|ico)$" ignoreCase="false" />
                    <action type="None" />
                </rule>
                <rule name="Imported rule 3" stopProcessing="true">
                    <match url="." ignoreCase="false" />
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}/index.html" matchType="IsFile" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}/index.php" matchType="IsFile" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
