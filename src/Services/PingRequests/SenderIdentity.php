<?php

namespace DiscordPings\Services\PingRequests;

/**
 * Who pressed a button. The text of a request can name any alt, so this is
 * what tells the FC which player is really behind it.
 */
class SenderIdentity
{
    public function __construct(
        public string $mainName,
        public ?string $corporation = null,
        public bool $connectorInstalled = false,
        public ?string $discordId = null,
        public ?string $discordName = null,
    ) {
    }

    /** Null means no connector on this install, so the line is left out altogether. */
    public function discordLine(): ?string
    {
        if (! $this->connectorInstalled) {
            return null;
        }

        if ($this->discordId === null || $this->discordId === '') {
            return 'not linked';
        }

        // The mention keeps showing their current server name. The stored name
        // beside it can be out of date, but it's still there if the mention
        // doesn't resolve for whoever reads it.
        $mention = '<@' . $this->discordId . '>';

        return $this->discordName ? $mention . ' (' . $this->discordName . ')' : $mention;
    }
}
