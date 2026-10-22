<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

/**
 * Storefront widget copy and theme, per store view. Defaults live only in etc/config.xml;
 * invalid colours are dropped so the stylesheet defaults apply.
 */
class WidgetConfig
{
    private const COPY = [
        'closeLabel' => 'widget/close_label',
        'clearLabel' => 'widget/clear_label',
        'inputPlaceholder' => 'widget/input_placeholder',
        'emailPlaceholder' => 'widget/email_placeholder',
        'sendLabel' => 'widget/send_label',
        'helper' => 'widget/helper_text',
        'userLabel' => 'widget/user_label',
        'contextLabel' => 'widget/context_label',
        'contextRemove' => 'widget/context_remove_label',
        'productLabel' => 'widget/product_label',
        'productAsk' => 'widget/product_ask_label',
        'productView' => 'widget/product_view_label',
        'welcomeEyebrow' => 'widget_welcome/welcome_eyebrow',
        'welcomeTitle' => 'widget_welcome/welcome_title',
        'welcomeCopy' => 'widget_welcome/welcome_copy',
        'connecting' => 'widget_messages/status_connecting',
        'ready' => 'widget_messages/status_ready',
        'thinking' => 'widget_messages/status_thinking',
        'unavailable' => 'widget_messages/status_unavailable',
        'messageFailed' => 'widget_messages/status_message_failed',
        'errorEyebrow' => 'widget_messages/error_eyebrow',
        'errorTitle' => 'widget_messages/error_title',
        'errorCopy' => 'widget_messages/error_copy',
        'errorRetry' => 'widget_messages/error_retry_label',
        'messageSendError' => 'widget_messages/message_send_error',
        'restoreProgress' => 'widget_messages/restore_progress',
        'restoreError' => 'widget_messages/restore_error',
    ];

    private const COLORS = [
        '--chat-primary' => 'widget_appearance/primary_color',
        '--chat-primary-hover' => 'widget_appearance/primary_hover_color',
        '--chat-on-primary' => 'widget_appearance/on_primary_color',
        '--chat-icon-bg' => 'widget_appearance/icon_background_color',
        '--chat-icon-fg' => 'widget_appearance/icon_foreground_color',
        '--chat-surface' => 'widget_appearance/surface_color',
        '--chat-canvas' => 'widget_appearance/canvas_color',
        '--chat-text' => 'widget_appearance/text_color',
        '--chat-muted' => 'widget_appearance/muted_color',
        '--chat-border' => 'widget_appearance/border_color',
    ];

    private const PROMPTS = ['one', 'two', 'three'];

    public function __construct(private readonly ConfigReader $reader)
    {
    }

    /**
     * @return array{title: string, icon: string, logoPath: string, copy: array<string, string>,
     *     prompts: list<array{label: string, text: string}>, colors: array<string, string>}
     */
    public function get(int $storeId): array
    {
        $copy = [];
        foreach (self::COPY as $key => $path) {
            $copy[$key] = $this->value($path, $storeId);
        }

        $prompts = [];
        foreach (self::PROMPTS as $ordinal) {
            $label = $this->value('widget_welcome/prompt_' . $ordinal . '_label', $storeId);
            $text = $this->value('widget_welcome/prompt_' . $ordinal . '_text', $storeId);
            if ($label !== '' && $text !== '') {
                $prompts[] = ['label' => $label, 'text' => $text];
            }
        }

        $colors = [];
        foreach (self::COLORS as $cssVariable => $path) {
            $value = $this->value($path, $storeId);
            if (preg_match('/^#[0-9a-f]{6}$/i', $value) === 1) {
                $colors[$cssVariable] = strtoupper($value);
            }
        }

        $logo = ltrim($this->value('widget/logo', $storeId), '/');

        return [
            'title' => $this->value('widget/title', $storeId),
            'icon' => $this->value('widget/icon', $storeId),
            'logoPath' => preg_match('#^[a-z0-9][a-z0-9._/-]*$#i', $logo) === 1 ? $logo : '',
            'copy' => $copy,
            'prompts' => $prompts,
            'colors' => $colors,
        ];
    }

    private function value(string $path, int $storeId): string
    {
        return $this->reader->string(ConfigReader::PREFIX . $path, $storeId);
    }
}
